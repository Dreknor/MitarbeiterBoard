<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\GradingStudentAnswer;
use App\Models\GradingStudentDevice;
use App\Services\GradingJoinService;
use App\Services\GradingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 – Selbsteinschätzung auf Schüler-iPads: Endpunkte für Schüler (ohne Konto).
 * Ein Schüler-Token erlaubt ausschließlich diese Endpunkte und nur für den eigenen Schüler.
 */
class StudentGradingApiController extends Controller
{
    public function __construct(
        private GradingJoinService $join,
        private GradingSessionService $sessions
    ) {
    }

    /**
     * POST /api/v1/student/join – öffentlich, Rate-Limit 10/min pro IP.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $result = $this->join->join($request->input('code'), $request->input('device_name'));

        if (!$result) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['code' => ['Der Code ist ungültig oder abgelaufen.']],
            ], 422);
        }

        [$device, $token] = $result;
        $session = $device->joinCode->session;

        return response()->json([
            'token' => $token,
            'expires_at' => $device->expires_at->toIso8601String(),
            'student' => ['firstname' => $device->joinCode->schueler->vorname],
            'session' => [
                'id' => $session->id,
                'answer_order_mode' => $session->answer_order_mode,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/student/session – freigegebene Fragen und eigene Selbsteinschätzungen.
     */
    public function session(Request $request): JsonResponse
    {
        /** @var GradingStudentDevice $device */
        $device = $request->user();
        $session = $device->session;

        $questions = $this->sessions->releasedQuestions($session);
        $ratings = GradingStudentAnswer::where('session_id', $session->id)
            ->where('schueler_id', $device->schueler_id)
            ->whereNotNull('self_rating')
            ->pluck('self_rating', 'question_id');

        // by_question: warten, bis die Lehrkraft die nächste Frage freigibt
        $waiting = $session->usesQuestionOrder()
            && $questions->every(fn ($q) => $ratings->has($q->id));

        return response()->json(['data' => [
            'session' => [
                'id' => $session->id,
                'answer_order_mode' => $session->answer_order_mode,
                'current_question_id' => $session->usesQuestionOrder() && $session->current_question_id ? (int) $session->current_question_id : null,
            ],
            'student' => ['firstname' => $device->schueler?->vorname],
            'questions' => $questions->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'sort_order' => (int) $q->sort_order,
            ])->values(),
            'self_ratings' => $ratings->map(fn ($rating, $questionId) => [
                'question_id' => (int) $questionId,
                'self_rating' => (int) $rating,
            ])->values(),
            'waiting' => $waiting,
        ]]);
    }

    /**
     * POST /api/v1/student/session/answers – schreibt ausschließlich die Selbsteinschätzung des eigenen Schülers.
     */
    public function storeAnswer(Request $request): JsonResponse
    {
        $request->validate([
            'question_id' => ['required', 'integer'],
            'self_rating' => ['required', 'integer', 'min:1', 'max:5'],
            // Pädagogenbewertung und Kommentar sind für Schüler nicht zugänglich
            'rating_value' => ['prohibited'],
            'comment' => ['prohibited'],
            'schueler_id' => ['prohibited'],
        ]);

        /** @var GradingStudentDevice $device */
        $device = $request->user();
        $session = $device->session;

        $questionId = $request->integer('question_id');
        if (!$this->sessions->releasedQuestions($session)->contains('id', $questionId)) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['question_id' => ['Die Frage ist nicht (mehr) freigegeben.']],
            ], 422);
        }

        $answer = GradingStudentAnswer::updateOrCreate(
            ['session_id' => $session->id, 'schueler_id' => $device->schueler_id, 'question_id' => $questionId],
            ['self_rating' => $request->integer('self_rating'), 'answered_at' => now()]
        );

        return response()->json(['data' => [
            'question_id' => (int) $answer->question_id,
            'self_rating' => (int) $answer->self_rating,
            'answered_at' => $answer->answered_at?->toIso8601String(),
        ]]);
    }
}
