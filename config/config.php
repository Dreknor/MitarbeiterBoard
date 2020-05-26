<?php

return [
    "themes"    => [
        'addDays'   => env('THEME_MIN_DAYS_BEFORE_MEETING', 4),
        'defaultDay'    => env('DEFAUL_WEEKDAY', 2),
        'maxDuration' => env('MAX_DURATION', 240),
    ]
];
