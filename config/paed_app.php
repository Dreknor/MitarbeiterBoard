<?php

/*
|--------------------------------------------------------------------------
| Pädagogen-App (API v1)
|--------------------------------------------------------------------------
| Einstellungen für die mobile Pädagogen-App. Laufzeitwerte, die Admins ändern
| sollen (Schulname), können zusätzlich über die settings-Tabelle
| (settings('school_name', 'paed_app')) überschrieben werden.
*/

return [

    // Version der API (GET /api/v1/instance) und minimal unterstützte App-Version
    'api_version' => '1.1.0',
    'min_app_version' => env('PAED_APP_MIN_VERSION', '1.0.0'),

    // Anzeige in der App (Serverwahl)
    'school_name' => env('PAED_APP_SCHOOL_NAME', env('APP_NAME') ?: 'MitarbeiterBoard'),
    'primary_color' => env('PAED_APP_PRIMARY_COLOR', '#1E40AF'),
    'sso_label' => env('PAED_APP_SSO_LABEL', 'Mit Schulkonto anmelden'),

    // Login mit lokalem Passwort (POST /api/v1/auth/token) erlauben
    'password_login' => (bool) env('PAED_APP_PASSWORD_LOGIN', true),

    // URL-Schema der App (Deep Links, QR-Codes)
    'url_scheme' => env('PAED_APP_URL_SCHEME', 'paeddiary'),

    // Erlaubte redirect_uri für den SSO-Login (kommagetrennt)
    'redirect_uris' => array_values(array_filter(array_map('trim', explode(',', env('PAED_APP_REDIRECT_URIS', 'paeddiary://auth'))))),

    // SSO: Gültigkeit des Einmal-Codes (Sekunden) und des Login-Vorgangs im Browser (Minuten)
    'sso_code_ttl' => 60,
    'sso_flow_ttl_minutes' => 10,

    // Token-Laufzeit in Tagen (gleitend, wird bei Nutzung verlängert)
    'token_days' => (int) env('PAED_APP_TOKEN_DAYS', 90),

    // Aufbewahrung von Idempotency-Keys (Stunden)
    'idempotency_hours' => 48,

    // Schüler-Beitrittscodes: maximale Gültigkeit (Stunden)
    'join_code_hours' => 8,
];
