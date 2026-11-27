<?php

/*
|--------------------------------------------------------------------------
| Angepasste Arbeitsblätter
|--------------------------------------------------------------------------
|
| Zugangsdaten (KI, Nextcloud) kommen aus der .env. Alles, was die Schule im
| Betrieb ändert (Ordnervorlagen, Limits, Automatik), steht in den Einstellungen
| (Modul „Angepasste Arbeitsblätter“, Seite /arbeitsblaetter/einstellungen).
|
*/

return [

    'ki' => [
        // keiner | anthropic | fake (nur Tests)
        'treiber' => env('AB_KI_TREIBER', 'keiner'),
        'modell' => env('AB_KI_MODELL', 'claude-opus-5-5'),
        'schluessel' => env('AB_KI_SCHLUESSEL', ''),
        'timeout' => (int) env('AB_KI_TIMEOUT', 180),
        'url' => env('AB_KI_URL', 'https://api.anthropic.com/v1/messages'),
        // Preise in Cent je 1 Mio. Token (Kostenschätzung für das Monatslimit, USD ≈ EUR)
        'preis_eingabe_cent' => (int) env('AB_KI_PREIS_EINGABE_CENT', 400),
        'preis_ausgabe_cent' => (int) env('AB_KI_PREIS_AUSGABE_CENT', 2000),
        // Aufwand der Erkennung (low|medium|high)
        'aufwand' => env('AB_KI_AUFWAND', 'medium'),
    ],

    // Nextcloud-Ablage der Fassungen und Word-Umwandlung (Standard: Dienstkonto des Wochenplans)
    'nextcloud' => [
        'url' => env('AB_NEXTCLOUD_URL', env('NEXTCLOUD_WOCHENPLAN_URL', env('NEXTCLOUD_URL', ''))),
        'username' => env('AB_NEXTCLOUD_USERNAME', env('NEXTCLOUD_WOCHENPLAN_USERNAME', env('NEXTCLOUD_USERNAME', ''))),
        'password' => env('AB_NEXTCLOUD_PASSWORD', env('NEXTCLOUD_WOCHENPLAN_PASSWORD', env('NEXTCLOUD_PASSWORD', ''))),
        'timeout' => (int) env('AB_NEXTCLOUD_TIMEOUT', 60),
        // Umwandlung DOC/DOCX/ODT → PDF über die Nextcloud (Collabora, ab Nextcloud 31)
        'konvertierung' => (bool) env('AB_NEXTCLOUD_KONVERTIERUNG', true),
    ],

    // Grenzen je Blatt
    'max_seiten' => (int) env('AB_MAX_SEITEN', 10),
    'max_upload_kb' => 20480,
    'max_bild_px' => 2400,

    // Entwürfe ohne Freigabe nach so vielen Tagen melden (nicht löschen)
    'entwurf_erinnerung_tage' => 90,
];
