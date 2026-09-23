<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\IssueTokenRequest;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * API v1: Token-Verwaltung (Laravel Sanctum).
 */
class AuthApiController extends Controller
{
    public function __construct(private ApiTokenService $tokens)
    {
    }

    /**
     * POST /api/v1/auth/token – Token für die Pädagogen-App ausstellen.
     * Nur für Benutzer mit lokalem Passwort (SSO-Konten nutzen /auth/sso/start).
     */
    public function issueToken(IssueTokenRequest $request): JsonResponse
    {
        if (!config('paed_app.password_login', true)) {
            return response()->json(['message' => 'Die Anmeldung mit Passwort ist auf diesem Server deaktiviert.'], 403);
        }

        /** @var User|null $user */
        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !$user->password || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['email' => ['Die Zugangsdaten sind ungültig.']],
            ], 422);
        }

        if (!$this->tokens->hasAppAccess($user)) {
            return response()->json(['message' => 'Keine Berechtigung für die Pädagogen-App.'], 403);
        }

        return response()->json($this->tokens->issue($user, $request->input('device_name')), 201);
    }

    /**
     * GET /api/v1/auth/me – Angemeldeter Benutzer inkl. Rechte (für die App-Oberfläche).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->tokens->userPayload($request->user())]);
    }

    /**
     * DELETE /api/v1/auth/token – aktuelles Token widerrufen (Logout).
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/auth/devices – eigene App-Geräte (Tokens).
     */
    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $request->user()->currentAccessToken()?->id;

        $devices = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'device_name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'is_current' => $currentId !== null && (int) $token->id === (int) $currentId,
            ])
            ->values();

        return response()->json(['data' => $devices]);
    }

    /**
     * DELETE /api/v1/auth/devices/{id} – eigenes Gerät abmelden (Token löschen).
     */
    public function destroyDevice(Request $request, int $device): JsonResponse
    {
        $token = $request->user()->tokens()->whereKey($device)->first();

        if (!$token) {
            return response()->json(['message' => 'Gerät nicht gefunden.'], 404);
        }

        $token->delete();

        return response()->json(null, 204);
    }
}
