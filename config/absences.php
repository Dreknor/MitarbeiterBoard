<?php
return [
    'absence_reason_default' => env('ABSENCE_REASON_DEFAULT', 'krank'),
    'absence_sick_note_days' => env('ABSENCE_SICK_NOTE_DAYS', 3),
    'absence_sick_note' => explode('|',env('ABSENCE_SICK_NOTE_REASONS', 'krank|Kind krank')),
    // Eigene Krankheit (ohne "Kind krank") – für Kurzerkrankungen und den BEM-Hinweis in /sick_notes
    'absence_own_sick' => explode('|', env('ABSENCE_OWN_SICK_REASONS', 'krank')),
];

