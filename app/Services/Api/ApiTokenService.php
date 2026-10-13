<?php

namespace App\Services\Api;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Pädagogen-App: Ausstellung und Laufzeit von App-Tokens (Laravel Sanctum).
 *
 * Genutzt von POST /auth/token (Passwort) und POST /auth/sso/exchange (SSO), damit beide
 * Wege identische Tokens und Antworten liefern.
 *
 * Zusätzlich: Anwendungstokens (API-Tokens), die Benutzer unter „Mein Profil“ selbst erstellen
 * (z.B. für Skripte/Integrationen). Sie tragen die Ability API_TOKEN_ABILITY, haben eine feste
 * Laufzeit (keine gleitende Verlängerung) und erscheinen nicht in der Geräteliste der App.
 */
class ApiTokenService
{
    public const ABILITY = 'paed-app';
    public const API_TOKEN_ABILITY = 'api-token';

    /** Wählbare Laufzeiten für Anwendungstokens (Tage => Bezeichnung) */
    public const API_TOKEN_LAUFZEITEN = [
        30 => '30 Tage',
        90 => '90 Tage',
        180 => '180 Tage',
        365 => '1 Jahr',
    ];

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
     * Erstellt ein Anwendungstoken mit fester Laufzeit. Der Klartext ist nur im Rückgabewert enthalten.
     */
    public function issueApiToken(User $user, string $name, int $days): NewAccessToken
    {
        $name = mb_substr($name, 0, 100);
        $token = $user->createToken($name, [self::API_TOKEN_ABILITY], now()->addDays(max(1, $days)));

        Log::info('API v1: Anwendungstoken erstellt', ['user_id' => $user->id, 'name' => $name, 'days' => $days]);

        return $token;
    }

    public function isApiToken(PersonalAccessToken $token): bool
    {
        return in_array(self::API_TOKEN_ABILITY, (array) $token->abilities, true);
    }

    /**
     * Tokens eines Benutzers getrennt nach App-Geräten und Anwendungstokens.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection} [Geräte, Anwendungstokens]
     */
    public function tokensFor(User $user): array
    {
        [$apiTokens, $devices] = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->partition(fn (PersonalAccessToken $token) => $this->isApiToken($token));

        return [$devices->values(), $apiTokens->values()];
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

        // Anwendungstokens behalten ihre beim Erstellen gewählte Laufzeit
        if ($this->isApiToken($token)) {
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
                'plan_rosters' => $can('create roster'),
            ],
        ];
    }
}
