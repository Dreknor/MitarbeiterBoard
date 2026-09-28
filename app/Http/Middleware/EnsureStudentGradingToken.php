<?php

namespace App\Http\Middleware;

use App\Models\GradingStudentDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1: Schüler-Endpunkte (Selbsteinschätzung auf Schüler-iPads).
 *
 * Entspricht Sanctums "ability:"-Middleware, prüft aber die dynamische Ability
 * "student-grading:{session_id}:{schueler_id}" gegen das Gerät des Tokens.
 * Ist die Session abgeschlossen, der Code widerrufen oder abgelaufen, ist das Token ungültig (401).
 */
class EnsureStudentGradingToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();

        if (!$device instanceof GradingStudentDevice) {
            return response()->json(['message' => 'Dieser Endpunkt ist nur für Schüler-Geräte.'], 403);
        }

        $token = $device->currentAccessToken();
        if (!$token || !$token->can($device->ability())) {
            return response()->json(['message' => 'Dieses Token ist für diesen Endpunkt nicht zugelassen.'], 403);
        }

        $device->loadMissing(['session', 'joinCode']);
        $session = $device->session;

        if (!$session || $session->isCompleted() || !$device->joinCode || $device->joinCode->expires_at->isPast()) {
            // Token endgültig entwerten
            $device->tokens()->delete();

            return response()->json(['message' => 'Nicht authentifiziert.'], 401);
        }

        return $next($request);
    }
}
