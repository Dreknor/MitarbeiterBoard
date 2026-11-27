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
        // keiner | anthropic | gemini | openrouter_auto | openrouter_free | fake (nur Tests)
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

        // Google Gemini (generateContent mit JSON-Schema)
        'gemini' => [
            'schluessel' => env('AB_GEMINI_SCHLUESSEL', env('AB_KI_SCHLUESSEL', '')),
            'modell' => env('AB_GEMINI_MODELL', 'gemini-flash-latest'),
            'url' => env('AB_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'preis_eingabe_cent' => (int) env('AB_GEMINI_PREIS_EINGABE_CENT', 50),
            'preis_ausgabe_cent' => (int) env('AB_GEMINI_PREIS_AUSGABE_CENT', 300),
        ],

        // OpenRouter (Chat Completions). openrouter_auto = Modell „openrouter/auto“ (wählt ein
        // passendes, kostenpflichtiges Modell), openrouter_free = „openrouter/free“ (zufälliges
        // kostenloses Modell – Vorsicht: kostenlose Anbieter dürfen Eingaben oft speichern/auswerten).
        'openrouter' => [
            'schluessel' => env('AB_OPENROUTER_SCHLUESSEL', env('AB_KI_SCHLUESSEL', '')),
            'url' => env('AB_OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
            'modell_auto' => env('AB_OPENROUTER_MODELL_AUTO', 'openrouter/auto'),
            'modell_free' => env('AB_OPENROUTER_MODELL_FREE', 'openrouter/free'),
            // Anbieter, die Eingaben speichern/zum Training nutzen, ausschließen (deny|allow)
            'datensammlung' => env('AB_OPENROUTER_DATENSAMMLUNG', 'deny'),
            // PDF-Verarbeitung: leer = nativ bzw. Standard von OpenRouter; „cloudflare-ai“ = kostenlos (Text)
            'pdf_engine_auto' => env('AB_OPENROUTER_PDF_ENGINE_AUTO', ''),
            'pdf_engine_free' => env('AB_OPENROUTER_PDF_ENGINE_FREE', 'cloudflare-ai'),
            // Kostenschätzung, falls OpenRouter keine Kosten meldet (Cent je 1 Mio. Token)
            'preis_eingabe_cent' => (int) env('AB_OPENROUTER_PREIS_EINGABE_CENT', 300),
            'preis_ausgabe_cent' => (int) env('AB_OPENROUTER_PREIS_AUSGABE_CENT', 1500),
        ],
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

    // Ordner mit lizenzierten Microsoft-Schriften (arial.ttf, arialbd.ttf, verdana.ttf, verdanab.ttf,
    // z. B. aus C:\Windows\Fonts). Fehlen sie, wird Arial durch Liberation Sans (maßgleich) und
    // Verdana durch DejaVu Sans ersetzt. Der Ordner wird nicht eingecheckt.
    'schriften_pfad' => env('AB_SCHRIFTEN_PFAD', storage_path('fonts/arbeitsblaetter')),

    // Grenzen je Blatt
    'max_seiten' => (int) env('AB_MAX_SEITEN', 10),
    'max_upload_kb' => 20480,
    'max_bild_px' => 2400,

    // Entwürfe ohne Freigabe nach so vielen Tagen melden (nicht löschen)
    'entwurf_erinnerung_tage' => 90,
];
