<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Services\GradingJoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 – Selbsteinschätzung auf Schüler-iPads: Endpunkte der Lehrkraft (nur Ersteller der Session).
 */
class GradingJoinApiController extends Controller
{
    public function __construct(private GradingJoinService $join)
    {
    }

    /**
     * POST /api/v1/grading/sessions/{session}/join-codes – Code je Schüler der Session.
     */
    public function store(GradingDocumentationSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        if ($session->isCompleted()) {
            return response()->json(['message' => 'Die Session ist bereits abgeschlossen.'], 409);
        }

        $codes = $this->join->createCodes($session, request()->user());

        return response()->json([
            'data' => $codes->map(fn ($code) => $this->join->payload($code))->values(),
        ]);
    }

    /**
     * DELETE /api/v1/grading/sessions/{session}/join-codes – alle Codes und Schüler-Tokens widerrufen.
     */
    public function destroy(GradingDocumentationSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $this->join->revoke($session);

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/grading/sessions/{session}/current-question – Frage für alle Schüler freigeben (by_question).
     */
    public function currentQuestion(Request $request, GradingDocumentationSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $request->validate(['question_id' => ['required', 'integer']]);

        if ($session->isCompleted()) {
            return response()->json(['message' => 'Die Session ist bereits abgeschlossen.'], 409);
        }

        if (!$session->usesQuestionOrder()) {
            return $this->unprocessable('question_id', 'Fragen können nur im Modus "by_question" freigegeben werden.');
        }

        $valid = GradingQuestion::whereKey($request->integer('question_id'))
            ->where('grading_system_id', $session->grading_system_id)
            ->where('active', true)
            ->exists();
        if (!$valid) {
            return $this->unprocessable('question_id', 'Die Frage gehört nicht zum Graduierungssystem der Session.');
        }

        $session->update(['current_question_id' => $request->integer('question_id')]);

        return response()->json(['data' => [
            'session_id' => $session->id,
            'current_question_id' => (int) $session->current_question_id,
        ]]);
    }

    private function unprocessable(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Die übermittelten Daten sind ungültig.',
            'errors' => [$field => [$message]],
        ], 422);
    }
}
