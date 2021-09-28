<?php
return [

    'themes'    => [
        'addDays'   => (is_numeric(env('THEME_MIN_DAYS_BEFORE_MEETING'))) ? env('THEME_MIN_DAYS_BEFORE_MEETING') : 3,
        'defaultDay'    => env('DEFAULT_WEEKDAY', 'Monday'),
        'maxDuration' => (is_numeric(env('MAX_DURATION'))) ? env('MAX_DURATION') : 240,
    ],

    //Anzahl der Tage vor Ablauf einer Aufgabe , wenn eine Erinnerung verschickt wird
    'tasks'      => [
        'remind'    => (is_numeric(env('REMIND_TASK'))) ? env('REMIND_TASK') : 2,
    ],

    'protocols'      => [
        'editableTime'    => (is_numeric(env('EDITABLE_TIME'))) ? env('EDITABLE_TIME') : 60,
    ],

    'url_elterninfo' => env('URL_ELTERNINFO', config('app.url')),

    //angezeigt Tage Vertretungsplan
    'show_vertretungen_days' => env('Show_Days', 2),
    'show_background' => env('BACKGROUND_IMAGE', ''),
];
