<?php

return [

    'themes'    => [
        'addDays'   => env('THEME_MIN_DAYS_BEFORE_MEETING', 3),
        'defaultDay'    => env('DEFAULT_WEEKDAY', 2),
        'maxDuration' => env('MAX_DURATION', 240),
    ],

    //Anzahl der Tage vor Ablauf einer Aufgabe , wenn eine Erinnerung verschickt wird
    'tasks'      => [
        'remind'    => env('REMIND_TASK', 2),
    ],

    'protocols'      => [
        'editableTime'    => env('EDITABLE_TIME', 60),
    ],
];
