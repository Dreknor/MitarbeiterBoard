<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\StoreDiagnosticSessionRequest;
use App\Http\Requests\API\v1\UpdateDiagnosticGoalRequest;
use App\Http\Resources\API\v1\CurrentCriterionGoalResource;
use App\Http\Resources\API\v1\DiagnosticAreaResource;
use App\Http\Resources\API\v1\DiagnosticGoalResource;
use App\Http\Resources\API\v1\DiagnosticSessionResource;
use App\Models\DiagnosticArea;
use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticSession;
use App\Models\DiagnosticStage;
use App\Models\Schueler;
use App\Services\Api\StudentDataService;
use App\Services\DiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API v1 – Bereich 4: Diagnose & Entwicklungsziele.
 * Alle Routen erfordern zusätzlich die Permission "view diagnostics".
 */
class DiagnosticApiController extends Controller
{
    public function __construct(
        private StudentDataService $data,
        private DiagnosticService $diagnosticService
    ) {
    }

    /**
     * GET /api/v1/diagnostic/areas – Katalog: Bereiche, Kompetenzstufen, Kriterien.
     */
    public function areas()
    {
        $areas = DiagnosticArea::active()
            ->ordered()
            ->with(['stages' => fn ($q) => $q->orderBy('sort_order'), 'stages.goals' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return DiagnosticAreaResource::collection($areas);
    }

    /**
     * GET /api/v1/students/{student}/diagnostic/history
     */
    public function history(Request $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('viewDiagnostics', $schueler);
        $request->validate(['include_archived_goals' => ['sometimes', 'in:true,false,1,0']]);

        $sessions = DiagnosticSession::where('schueler_id', $schueler->id)
            ->with(['area:id,name', 'user:id,name', 'stageNotes.stage:id,name', 'assessments', 'developmentGoals.area:id,name', 'developmentGoals.creator:id,name'])
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => [
            'sessions' => DiagnosticSessionResource::collection($sessions),
            'development_goals' => DiagnosticGoalResource::collection(
                $this->data->developmentGoals($schueler, false, $request->boolean('include_archived_goals'))
            ),
            'current_criterion_goals' => CurrentCriterionGoalResource::collection($this->data->currentCriterionGoals($schueler)),
        ]]);
    }

    /**
     * POST /api/v1/diagnostic/sessions – Diagnosesitzung erfassen & Entwicklungsziele eintragen.
     * Ist für Schüler und Bereich bereits eine offene Sitzung vorhanden (z.B. im Web begonnen),
     * wird diese fortgeführt (wie DiagnosticService::getOrCreateSession).
     */
    public function storeSession(StoreDiagnosticSessionRequest $request): JsonResponse
    {
        $user = $request->user();
        $schueler = Schueler::findOrFail($request->integer('schueler_id'));
        $this->authorize('viewDiagnostics', $schueler);

        $area = DiagnosticArea::findOrFail($request->integer('area_id'));
        if (!$area->active) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['area_id' => ['Der Diagnosebereich ist nicht aktiv.']],
            ], 422);
        }

        $stage = $request->filled('stage_id') ? DiagnosticStage::find($request->integer('stage_id')) : null;
        $complete = $request->has('complete') ? $request->boolean('complete') : true;

        $resumed = false;
        $session = DB::transaction(function () use ($request, $user, $schueler, $area, $stage, $complete, &$resumed) {
            $session = DiagnosticSession::where('schueler_id', $schueler->id)
                ->where('diagnostic_area_id', $area->id)
                ->where('is_completed', false)
                ->lockForUpdate()
                ->first();

            $resumed = $session !== null;
            if (!$session) {
                $session = DiagnosticSession::create([
                    'schueler_id' => $schueler->id,
                    'diagnostic_area_id' => $area->id,
                    'user_id' => $user->id,
                    'session_date' => $request->input('session_date', now()->toDateString()),
                    'started_at' => now(),
                    'is_completed' => false,
                ]);
            }

            // Notizen: mit Stufe als Stufen-Notiz, sonst als Sitzungsnotiz
            if ($request->filled('assessment_notes')) {
                if ($stage) {
                    $this->diagnosticService->saveStageNote($session, $stage, $request->input('assessment_notes'));
                } else {
                    $session->update(['notes' => $request->input('assessment_notes')]);
                }
            }

            // Bewertung von Katalogkriterien
            foreach ($request->input('assessments', []) as $item) {
                $criterion = DiagnosticGoal::find($item['criterion_id']);
                $assessment = $this->diagnosticService->saveAssessment($session, $criterion, $item['rating'] ?? null);
                if (array_key_exists('is_current_goal', $item)) {
                    $assessment->update(['is_current_goal' => (bool) $item['is_current_goal']]);
                }
            }

            // Individuelle Entwicklungsziele
            foreach ($request->input('goals', []) as $goal) {
                $criterion = !empty($goal['criterion_id']) ? DiagnosticGoal::with('stage')->find($goal['criterion_id']) : null;
                DiagnosticDevelopmentGoal::create([
                    'schueler_id' => $schueler->id,
                    'diagnostic_session_id' => $session->id,
                    'diagnostic_area_id' => $area->id,
                    'diagnostic_stage_id' => $criterion?->diagnostic_stage_id ?? $stage?->id,
                    'diagnostic_goal_id' => $criterion?->id,
                    'title' => $goal['title'],
                    'target_date' => $goal['target_date'] ?? null,
                    'status' => DiagnosticDevelopmentGoal::STATUS_IN_PROGRESS,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            if ($complete && !$session->is_completed) {
                $this->diagnosticService->completeSession($session);
            }

            return $session;
        });

        $session = $session->fresh()->load([
            'area:id,name', 'user:id,name', 'stageNotes.stage:id,name', 'assessments',
            'developmentGoals.area:id,name', 'developmentGoals.creator:id,name',
        ]);

        return (new DiagnosticSessionResource($session))
            ->additional(['meta' => ['resumed_open_session' => $resumed]])
            ->response()
            ->setStatusCode($resumed ? 200 : 201);
    }

    /**
     * PUT /api/v1/diagnostic/goals/{goal} – Status, Zieldatum, Titel oder Abschlussnotiz ändern.
     */
    public function updateGoal(UpdateDiagnosticGoalRequest $request, DiagnosticDevelopmentGoal $goal)
    {
        $this->authorize('viewDiagnostics', $goal->schueler ?? abort(404));

        $attributes = $request->safe()->only(['title', 'target_date', 'completion_notes', 'status']);

        if ($request->has('status')) {
            $status = $request->input('status');
            $attributes['archived_at'] = null; // Reaktivieren eines archivierten Ziels

            if (in_array($status, DiagnosticDevelopmentGoal::COMPLETED_STATUSES, true)) {
                $attributes['completed_at'] = $request->input('completed_at') ?? $goal->completed_at?->toDateString() ?? now()->toDateString();
            } else {
                $attributes['completed_at'] = null;
            }
        } elseif ($request->has('completed_at')) {
            $attributes['completed_at'] = $request->input('completed_at');
        }

        $attributes['updated_by'] = $request->user()->id;
        $goal->update($attributes);

        return new DiagnosticGoalResource($goal->fresh()->load(['area:id,name', 'creator:id,name']));
    }

    /**
     * DELETE /api/v1/diagnostic/goals/{goal}
     * Standard: Archivieren (Status "archived"). Mit ?force=true endgültig löschen ("manage diagnostics").
     */
    public function destroyGoal(Request $request, DiagnosticDevelopmentGoal $goal)
    {
        $this->authorize('viewDiagnostics', $goal->schueler ?? abort(404));
        $request->validate(['force' => ['sometimes', 'in:true,false,1,0']]);

        if ($request->boolean('force')) {
            abort_unless($request->user()->can('manage diagnostics'), 403, 'Endgültiges Löschen erfordert die Berechtigung "manage diagnostics".');
            $goal->delete();

            return response()->json(null, 204);
        }

        $goal->update([
            'status' => DiagnosticDevelopmentGoal::STATUS_ARCHIVED,
            'archived_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return new DiagnosticGoalResource($goal->fresh()->load(['area:id,name', 'creator:id,name']));
    }
}
