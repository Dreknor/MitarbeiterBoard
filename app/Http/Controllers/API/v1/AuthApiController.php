<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * API v1: Token-Verwaltung (Laravel Sanctum).
 */
class AuthApiController extends Controller
{
    /**
     * POST /api/v1/auth/token – Token für die Pädagogen-App ausstellen.
     * Nur für Benutzer mit lokalem Passwort (SSO-Konten besitzen kein prüfbares Passwort).
     */
    public function issueToken(IssueTokenRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !$user->password || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['email' => ['Die Zugangsdaten sind ungültig.']],
            ], 422);
        }

        if (!$this->hasAppAccess($user)) {
            return response()->json(['message' => 'Keine Berechtigung für die Pädagogen-App.'], 403);
        }

        $token = $user->createToken(mb_substr($request->input('device_name'), 0, 100), ['paed-app']);

        Log::info('API v1: Token ausgestellt', ['user_id' => $user->id, 'device' => $request->input('device_name')]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String()
                ?? (config('sanctum.expiration') ? now()->addMinutes((int) config('sanctum.expiration'))->toIso8601String() : null),
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * GET /api/v1/auth/me – Angemeldeter Benutzer inkl. Rechte (für die App-Oberfläche).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    /**
     * DELETE /api/v1/auth/token – aktuelles Token widerrufen (Logout).
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    private function hasAppAccess(User $user): bool
    {
        try {
            return $user->hasPermissionTo('view paed diary');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }

    private function userPayload(User $user): array
    {
        $can = function (string $perm) use ($user): bool {
            try {
                return $user->hasPermissionTo($perm);
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                return false;
            }
        };

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => [
                'view_diagnostics' => $can('view diagnostics'),
                'manage_grading' => $can('manage grading systems'),
                'view_all_students' => $user->canAccessAllStudents(),
                'view_confidential_entries' => $user->canViewConfidentialDiaryEntries(),
            ],
        ];
    }
}
