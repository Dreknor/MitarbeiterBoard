<?php
return [
    'absence_reason_default' => env('ABSENCE_REASON_DEFAULT', 'krank'),
    'absence_sick_note_days' => env('ABSENCE_SICK_NOTE_DAYS', 3),
    'absence_sick_note' => explode('|',env('ABSENCE_REASON_DEFAULT', 'krank|Kind krank')),
];
