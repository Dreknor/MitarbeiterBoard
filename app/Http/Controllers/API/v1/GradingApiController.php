<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\StoreGradingAssessmentRequest;
use App\Http\Requests\API\v1\StoreGradingSessionRequest;
use App\Http\Resources\API\v1\GradingSessionResource;
use App\Http\Resources\API\v1\GradingStageResource;
use App\Models\GradingCoachingNote;
use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingStage;
use App\Models\GradingStudentAnswer;
use App\Models\GradingSystem;
use App\Models\GradingTeacherAssessment;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Services\Api\StudentDataService;
use App\Services\GradingStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API v1 – Bereich 3: Graduierung (Bewertungssessions, Stufenvergabe).
 */
class GradingApiController extends Controller
{
    public function __construct(
        private StudentDataService $data,
        private GradingStageService $stageService
    ) {
    }

    /**
     * GET /api/v1/grading/stages – Stammkatalog aller Stufen aktiver Graduierungssysteme.
     * Optional: ?grading_system_id=… oder ?class_id=… (Stufen des Systems der Klasse).
     */
    public function stages(Request $request)
    {
        $request->validate([
            'grading_system_id' => ['sometimes', 'integer'],
            'class_id' => ['sometimes', 'integer', 'exists:klassen,id'],
        ]);

        $systemId = $request->input('grading_system_id');
        if ($request->filled('class_id')) {
            $klasse = Klasse::findOrFail($request->integer('class_id'));
            $this->authorize('viewClass', [Schueler::class, $klasse]);
            $systemId = $klasse->grading_system_id ?? 0;
        }

        $systems = GradingSystem::query()
            ->when($systemId !== null, fn ($q) => $q->whereKey($systemId), fn ($q) => $q->where('active', true))
            ->with('stages')
            ->orderBy('name')
            ->get();

        $stages = $systems->flatMap(fn ($s) => $s->stages)->values();

        return GradingStageResource::collection($stages)->additional([
            'meta' => [
                'grading_systems' => $systems->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'active' => (bool) $s->active,
                ])->values(),
            ],
        ]);
    }

    /**
     * GET /api/v1/students/{student}/grading/history
     */
    public function history(Request $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('view', $schueler);
        $schueler->loadMissing('grading_stage');

        $sessions = $this->data->completedGradingSessions($schueler);

        return response()->json(['data' => [
            'current_stage' => $this->data->currentStage($schueler),
            'stage_history' => $this->data->gradingHistory($schueler),
            'completed_sessions' => GradingSessionResource::collection($sessions),
        ]]);
    }

    /**
     * POST /api/v1/grading/sessions – individuelle Bewertungssession starten.
     * Existiert bereits eine offene Session des Benutzers für den Schüler, wird diese fortgesetzt (200).
     */
    public function storeSession(StoreGradingSessionRequest $request): JsonResponse
    {
        $user = $request->user();
        $schueler = Schueler::with('klasse')->findOrFail($request->integer('schueler_id'));
        $this->authorize('update', $schueler);

        $klasse = $schueler->klasse;
        if (!$klasse || !$klasse->grading_system_id) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['schueler_id' => ['Der Klasse des Schülers ist kein Graduierungssystem zugeordnet.']],
            ], 422);
        }

        $session = GradingDocumentationSession::where('user_id', $user->id)
            ->where('klasse_id', $klasse->id)
            ->where('type', 'individual')
            ->where('schueler_id', $schueler->id)
            ->whereNull('completed_at')
            ->first();

        $resumed = $session !== null;

        if (!$session) {
            $session = GradingDocumentationSession::create([
                'klasse_id' => $klasse->id,
                'grading_system_id' => $klasse->grading_system_id,
                'user_id' => $user->id,
                'type' => 'individual',
                'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
                'schueler_id' => $schueler->id,
                'started_at' => now(),
            ]);
        }

        return (new GradingSessionResource($this->loadSession($session)))
            ->additional(['meta' => ['resumed' => $resumed]])
            ->response()
            ->setStatusCode($resumed ? 200 : 201);
    }

    /**
     * GET /api/v1/grading/sessions/{session} – Fragenkatalog & Zwischenstand.
     */
    public function showSession(GradingDocumentationSession $session)
    {
        $this->authorize('view', $session);

        $stages = GradingStage::where('grading_system_id', $session->grading_system_id)->orderBy('sort_order')->get();

        return (new GradingSessionResource($this->loadSession($session)))->additional([
            'meta' => ['available_stages' => GradingStageResource::collection($stages)],
        ]);
    }

    /**
     * POST /api/v1/grading/sessions/{session}/assessments
     * Antworten & Pädagogenbewertung speichern, optional abschließen und Stufe vergeben.
     */
    public function storeAssessments(StoreGradingAssessmentRequest $request, GradingDocumentationSession $session): JsonResponse
    {
        $this->authorize('update', $session);
        $user = $request->user();

        if ($session->isCompleted()) {
            return response()->json(['message' => 'Die Session ist bereits abgeschlossen.'], 409);
        }

        // Schüler bestimmen
        if ($session->isIndividualSession()) {
            if ($request->filled('schueler_id') && (int) $request->input('schueler_id') !== (int) $session->schueler_id) {
                return $this->unprocessable('schueler_id', 'Der Schüler gehört nicht zu dieser Session.');
            }
            $schuelerId = (int) $session->schueler_id;
        } else {
            if (!$request->filled('schueler_id')) {
                return $this->unprocessable('schueler_id', 'Bei Gruppen-Sessions ist schueler_id erforderlich.');
            }
            $schuelerId = $request->integer('schueler_id');
            if (!Schueler::whereKey($schuelerId)->where('klasse_id', $session->klasse_id)->exists()) {
                return $this->unprocessable('schueler_id', 'Der Schüler gehört nicht zur Klasse der Session.');
            }
        }
        $schueler = Schueler::with('klasse')->findOrFail($schuelerId);

        // Fragen müssen zum Graduierungssystem der Session gehören
        $answers = collect($request->input('answers', []));
        $questionIds = $answers->pluck('question_id')->map(fn ($id) => (int) $id);
        if ($questionIds->isNotEmpty()) {
            $valid = GradingQuestion::whereIn('id', $questionIds)
                ->where('grading_system_id', $session->grading_system_id)
                ->pluck('id');
            $invalid = $questionIds->diff($valid);
            if ($invalid->isNotEmpty()) {
                return $this->unprocessable('answers', 'Folgende Fragen gehören nicht zum Graduierungssystem der Session: ' . $invalid->implode(', '));
            }
        }

        // Stufenvergabe prüfen
        $finalize = $request->boolean('finalize');
        $changeStage = $finalize && $request->has('grading_stage_id');
        $newStage = null;
        if ($changeStage) {
            $this->authorize('changeGradingStage', $schueler);
            if ($request->filled('grading_stage_id')) {
                $newStage = GradingStage::findOrFail($request->integer('grading_stage_id'));
                if ((int) $newStage->grading_system_id !== (int) $session->grading_system_id) {
                    return $this->unprocessable('grading_stage_id', 'Die Stufe gehört nicht zum Graduierungssystem der Session.');
                }
            }
        }

        DB::transaction(function () use ($answers, $session, $schueler, $request, $user, $finalize, $changeStage, $newStage) {
            $now = now();

            foreach ($answers as $answer) {
                $key = [
                    'session_id' => $session->id,
                    'schueler_id' => $schueler->id,
                    'question_id' => (int) $answer['question_id'],
                ];

                if (array_key_exists('rating_value', $answer) || array_key_exists('comment', $answer)) {
                    $existing = GradingTeacherAssessment::where($key)->first();
                    GradingTeacherAssessment::updateOrCreate($key, [
                        'teacher_rating' => array_key_exists('rating_value', $answer) ? $answer['rating_value'] : $existing?->teacher_rating,
                        'comment' => array_key_exists('comment', $answer) ? $answer['comment'] : $existing?->comment,
                        'assessed_at' => $now,
                    ]);
                }

                if (array_key_exists('self_rating', $answer) && $answer['self_rating'] !== null) {
                    GradingStudentAnswer::updateOrCreate($key, [
                        'self_rating' => $answer['self_rating'],
                        'answered_at' => $now,
                    ]);
                }
            }

            if ($request->has('teacher_assessment')) {
                GradingCoachingNote::updateOrCreate(
                    ['session_id' => $session->id, 'schueler_id' => $schueler->id],
                    ['user_id' => $user->id, 'note' => $request->input('teacher_assessment'), 'noted_at' => $now]
                );
            }

            if ($finalize) {
                $session->update(['completed_at' => $now]);
            }

            if ($changeStage && (int) $schueler->grading_stage_id !== (int) $newStage?->id) {
                $this->stageService->changeStage($schueler, $newStage, $user, $schueler->klasse);
            }
        });

        $schueler->refresh()->load('grading_stage');

        return (new GradingSessionResource($this->loadSession($session->fresh())))->additional(['meta' => [
            'finalized' => $finalize,
            'current_stage' => $this->data->currentStage($schueler),
        ]])->response();
    }

    private function loadSession(GradingDocumentationSession $session): GradingDocumentationSession
    {
        return $session->load([
            'user:id,name',
            'gradingSystem:id,name',
            'gradingSystem.questions' => fn ($q) => $q->where('active', true)->orderBy('sort_order'),
            'studentAnswers',
            'teacherAssessments',
            'coachingNotes',
        ]);
    }

    private function unprocessable(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Die übermittelten Daten sind ungültig.',
            'errors' => [$field => [$message]],
        ], 422);
    }
}
