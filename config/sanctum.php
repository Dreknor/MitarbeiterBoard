<?php

// SANCTUM_EXPIRATION: Minuten (Standard 30 Tage); "null" = kein Ablauf (env() liefert dann null).
$sanctumExpiration = env('SANCTUM_EXPIRATION', 60 * 24 * 30);

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
    | Ablauf der Tokens (Minuten)
    |--------------------------------------------------------------------------
    |
    | Standard: 30 Tage. Mit SANCTUM_EXPIRATION=null in der .env laufen Tokens
    | nicht ab (nicht empfohlen, da die App personenbezogene Schülerdaten liest).
    |
    */

    'expiration' => $sanctumExpiration === null ? null : (int) $sanctumExpiration,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'mb_'),

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

];
