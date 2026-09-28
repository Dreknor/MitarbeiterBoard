<?php

namespace App\Services\Api;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pädagogen-App: SSO-Login über den bestehenden Keycloak-Login des Backends
 * (Authorization Code + PKCE zwischen App und Backend, Keycloak bleibt unverändert).
 *
 * 1. GET  /api/v1/auth/sso/start   → startFlow(): Daten in die Session, Keycloak-Redirect
 * 2. GET  /auth/callback           → completeFlow(): Einmal-Code, Redirect in die App
 * 3. POST /api/v1/auth/sso/exchange → redeem(): Code + code_verifier gegen Token tauschen
 */
class AppSsoService
{
    private const SESSION_KEY = 'paed_app_sso';
    private const CACHE_PREFIX = 'paed_app_sso_code:';

    public function startFlow(string $redirectUri, string $codeChallenge, string $state): void
    {
        session()->put(self::SESSION_KEY, [
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'state' => $state,
            'started_at' => now()->getTimestamp(),
        ]);
    }

    /**
     * Läuft im Browser gerade ein (nicht abgelaufener) App-Login?
     */
    public function hasFlow(): bool
    {
        $flow = $this->flow();

        if (!$flow) {
            return false;
        }

        $maxAge = (int) config('paed_app.sso_flow_ttl_minutes', 10) * 60;
        if (now()->getTimestamp() - (int) ($flow['started_at'] ?? 0) > $maxAge) {
            $this->forgetFlow();

            return false;
        }

        return true;
    }

    public function forgetFlow(): void
    {
        if (app()->bound('session') && request()->hasSession()) {
            session()->forget(self::SESSION_KEY);
        }
    }

    /**
     * Nach erfolgreicher Nutzerzuordnung: Einmal-Code erzeugen und in die App weiterleiten.
     */
    public function completeFlow(User $user): RedirectResponse
    {
        $flow = $this->flow();
        $this->forgetFlow();

        $code = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        Cache::put(self::CACHE_PREFIX . hash('sha256', $code), [
            'user_id' => $user->id,
            'code_challenge' => $flow['code_challenge'],
        ], now()->addSeconds((int) config('paed_app.sso_code_ttl', 60)));

        Log::info('API v1: SSO-Einmal-Code ausgestellt', ['user_id' => $user->id]);

        return redirect()->away($this->appRedirect($flow, ['code' => $code]));
    }

    /**
     * Fehler beim Keycloak-Login → paeddiary://auth?error=<code>&state=<state>
     */
    public function errorRedirect(string $error): RedirectResponse
    {
        $flow = $this->flow();
        $this->forgetFlow();

        return redirect()->away($this->appRedirect($flow, ['error' => $error]));
    }

    /**
     * Löst einen Einmal-Code ein. Der Code wird bei jedem Versuch verbraucht (einmal verwendbar).
     *
     * @return array{user_id: int}|string  Nutzdaten oder Fehlerfeld ("code" | "code_verifier")
     */
    public function redeem(string $code, string $codeVerifier): array|string
    {
        $key = self::CACHE_PREFIX . hash('sha256', $code);

        $data = Cache::lock($key . ':lock', 5)->get(fn () => Cache::pull($key));

        if (!is_array($data)) {
            return 'code';
        }

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        if (!hash_equals((string) $data['code_challenge'], $challenge)) {
            return 'code_verifier';
        }

        return ['user_id' => (int) $data['user_id']];
    }

    private function flow(): ?array
    {
        if (!app()->bound('session') || !request()->hasSession()) {
            return null;
        }

        $flow = session(self::SESSION_KEY);

        return is_array($flow) ? $flow : null;
    }

    private function appRedirect(?array $flow, array $params): string
    {
        $redirectUri = $flow['redirect_uri'] ?? (config('paed_app.redirect_uris')[0] ?? 'paeddiary://auth');
        $params['state'] = $flow['state'] ?? '';

        return $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
