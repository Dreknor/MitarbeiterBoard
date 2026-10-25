<?php

/*
|--------------------------------------------------------------------------
| Löschkonzept für Benutzerkonten
|--------------------------------------------------------------------------
|
| Stufe 1: Deaktivieren (Soft-Delete, umkehrbar)
| Stufe 2: Anonymisieren (nach Sperrfrist, nur mit Admin-Bestätigung)
| Stufe 3: Endgültig löschen (nach Ablauf aller Aufbewahrungsfristen, nur mit Admin-Bestätigung)
|
| Fristen laufen ab dem Ende des Kalenderjahres, in dem der Austritt (users.left_at) lag.
| Ausnahmen (Sperrfrist, Führungszeugnis) laufen ab dem Austrittsdatum selbst.
|
*/

return [

    // Sperrfrist zwischen Deaktivierung und Anonymisierung (Monate ab Austritt)
    'anonymize_after_months' => env('USER_DELETION_ANONYMIZE_AFTER_MONTHS', 12),

    // § 72a Abs. 5 SGB VIII: Daten zum Führungszeugnis spätestens 3 Monate nach Ende der Tätigkeit löschen
    'fuehrungszeugnis_months' => 3,
    'fuehrungszeugnis_pattern' => '%Führungszeugnis%',

    // Aufbewahrungskategorien in Jahren (ab Ende des Austrittsjahres)
    'retention' => [
        // Lohn- und Entgeltgrundlagen: § 147 AO, § 257 HGB (Board ist Grundlage der Lohnabrechnung)
        'payroll' => 8,
        // Ansprüche ohne Entgeltbezug: §§ 195, 199 BGB
        'claims' => 3,
        // Zugriffsprotokolle auf Personaldaten
        'access_log' => 2,
    ],

    // Name des Platzhalter-Kontos, auf das anonymisierte Inhalte verweisen
    'placeholder_name' => 'Ehemalige Person',
];
