<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CalDAV-Verbindung
    |--------------------------------------------------------------------------
    |
    | Credentials für das OX-Dienstkonto. NIEMALS in der DB-Settings-Tabelle
    | speichern – nur in .env.
    |
    */

    'url'      => env('OX_CALDAV_URL', ''),
    'username' => env('OX_CALDAV_USERNAME', ''),
    'password' => env('OX_CALDAV_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Feature-Toggle
    |--------------------------------------------------------------------------
    */

    'enabled' => env('OX_CALENDAR_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | SSL-Verifikation
    |--------------------------------------------------------------------------
    |
    | In Production IMMER true. Nur in lokaler Entwicklung mit selbst-signierten
    | Zertifikaten auf false setzen.
    |
    */

    'verify_ssl' => env('OX_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout für CalDAV-Requests in Sekunden.
    |
    */

    'timeout' => env('OX_CALDAV_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Sync-Horizont
    |--------------------------------------------------------------------------
    |
    | Maximale Monate voraus für Sync. Termine die weiter in der Zukunft liegen,
    | werden nicht synchronisiert.
    |
    */

    'sync_months_ahead' => 6,

];

