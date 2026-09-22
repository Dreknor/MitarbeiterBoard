<?php

return [
    /*
     * Prüfengine für Zeiterfassung, Dienstpläne & Vertragsänderungen
     * (siehe docs Konzept "Automatisierte Prüfengine").
     */

    // Schwellenwert für ROSTER_DEVIATION in Minuten (Soll- vs. Ist-Abweichung pro Tag)
    'roster_deviation_threshold_minutes' => env('TIMESHEET_ROSTER_DEVIATION_THRESHOLD', 30),

    // Wochentage-Grenze für MISSING_CLOCK_OUT (wie viele Tage in die Vergangenheit noch geprüft werden)
    'missing_clock_out_lookback_days' => env('TIMESHEET_MISSING_CLOCKOUT_LOOKBACK', 62),
];

