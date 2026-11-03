<?php

namespace App\Services\Api;

/**
 * Pädagogen-App: Instanz-Informationen und Deep Links (Serverwahl, QR-Codes).
 */
class PaedAppService
{
    public function schoolName(): string
    {
        return (string) (settings('school_name', 'paed_app') ?: config('app.name') ?: 'MitarbeiterBoard');
    }

    public function logoUrl(): ?string
    {
        $logo = config('app.logo');

        return $logo ? asset('img/' . $logo) : null;
    }

    public function passwordLoginEnabled(): bool
    {
        return (bool) config('paed_app.password_login', true);
    }

    /** SSO ist verfügbar, wenn die Keycloak-Verbindung konfiguriert ist. */
    public function ssoEnabled(): bool
    {
        return filled(config('services.keycloak.client_id'))
            && filled(config('services.keycloak.base_url'))
            && filled(config('services.keycloak.realms'));
    }

    /** Basis-URL dieses Servers ohne abschließenden Slash, z.B. https://mitarbeiter.schule.de */
    public function serverUrl(): string
    {
        return rtrim(url('/'), '/');
    }

    /** paeddiary://connect?server=https://… (Inhalt des QR-Codes „App verbinden“) */
    public function connectUrl(): string
    {
        return $this->deepLink('connect', ['server' => $this->serverUrl()]);
    }

    /**
     * Deep Link in die App. Die Server-URL bleibt wie spezifiziert unkodiert lesbar
     * (":" und "/" sind in Query-Strings zulässig).
     */
    public function deepLink(string $path, array $params = []): string
    {
        $query = collect($params)
            ->map(fn ($value, $key) => $key . '=' . ($key === 'server' ? $value : rawurlencode((string) $value)))
            ->implode('&');

        return config('paed_app.url_scheme', 'paeddiary') . '://' . $path . ($query !== '' ? '?' . $query : '');
    }
}
