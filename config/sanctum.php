<?php

// SANCTUM_EXPIRATION: optionale absolute Höchstlaufzeit ab Ausstellung (Minuten).
// Standard null: Die Laufzeit steuert expires_at je Token (gleitend, PAED_APP_TOKEN_DAYS, siehe config/paed_app.php).
$sanctumExpiration = env('SANCTUM_EXPIRATION');

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Die Pädagogen-App nutzt ausschließlich Bearer-Tokens. Cookie-basierte
    | SPA-Authentifizierung ist nicht vorgesehen, daher keine stateful Domains.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Absolute Höchstlaufzeit der Tokens (Minuten, ab Ausstellung)
    |--------------------------------------------------------------------------
    |
    | App-Tokens erhalten ein eigenes expires_at (jetzt + PAED_APP_TOKEN_DAYS, Standard 90),
    | das bei Nutzung höchstens einmal täglich verlängert wird. Ein hier gesetzter Wert
    | begrenzt zusätzlich die Gesamtlaufzeit seit der Ausstellung (sonst: kein Limit).
    |
    */

    'expiration' => $sanctumExpiration === null ? null : (int) $sanctumExpiration,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'mb_'),

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

];
