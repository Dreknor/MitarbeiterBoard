<?php

namespace App\Services\Api;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Pädagogen-App: Ausstellung und Laufzeit von App-Tokens (Laravel Sanctum).
 *
 * Genutzt von POST /auth/token (Passwort) und POST /auth/sso/exchange (SSO), damit beide
 * Wege identische Tokens und Antworten liefern.
 */
class ApiTokenService
{
    public const ABILITY = 'paed-app';

    public function hasAppAccess(User $user): bool
    {
        try {
            return $user->hasPermissionTo('view paed diary');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Stellt ein App-Token aus und liefert den Antwort-Body (Status 201).
     */
    public function issue(User $user, string $deviceName, string $via = 'password'): array
    {
        $deviceName = mb_substr($deviceName, 0, 100);
        $token = $user->createToken($deviceName, [self::ABILITY], $this->nextExpiry());

        Log::info('API v1: Token ausgestellt', ['user_id' => $user->id, 'device' => $deviceName, 'via' => $via]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
        ];
    }

    /**
     * Gleitende Laufzeit: Bei Nutzung auf "jetzt + token_days" verlängern,
     * höchstens einmal pro Tag in die Datenbank schreiben.
     */
    public function extend(PersonalAccessToken $token): void
    {
        // Nur gespeicherte Tokens (nicht z.B. Sanctum::actingAs() in Tests)
        if (!$token->exists) {
            return;
        }

        $target = $this->nextExpiry();
        $expiresAt = $token->expires_at;

        if (!$expiresAt instanceof \DateTimeInterface || $expiresAt->lt($target->copy()->subDay())) {
            $token->forceFill(['expires_at' => $target])->save();
        }
    }

    public function nextExpiry(): \Illuminate\Support\Carbon
    {
        return now()->addDays(max(1, (int) config('paed_app.token_days', 90)));
    }

    public function userPayload(User $user): array
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
