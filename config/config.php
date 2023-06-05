<?php
return [
    'logo_small' => env('APP_LOGO_SMALL', 'logo-small.png'),

    'abwesenheiten_arbeitszeit' => [
        'Urlaub' => 100,
        'Fortbildung' => 100,
        'Weiterbildung' => 100,
        'krank' => 100,
        'Kind krank' => 100,
        'eingeschränkter Regelbetrieb' => 100,
        'eingeschr. Regelbetrieb' => 100,
        'Corona'    => 100,
        'Homeoffice'    => 100,
        'Kur'   => 100,
        'Bildungstag' => 100,
        'Entlastungstag' => 100,
        'Feiertag' => 100,
        'Vorbereitungszeit' => 15,
        'Ausgleichstag' => 100,
        'Klassenfahrt' => 100,

    ],

    'auth' => [
        'auth_local' => env('AUTH_LOCAL', true),
        'saml2' => env("SAML2_ENABLE",false),
        'saml2_btn' => env("SAML2_BUTTON_TEXT",'SSO - Login'),
        'set_groups' => explode('|',env('DEFAULT_GROUPS', '')),
        'saml_member_of_prefix' => env('SAML_PREFIX',''),
        'set_roles' => explode('|',env('DEFAULT_ROLES', '')),
    ],

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

    //Schuljahresbeginn
    'schuljahresbeginn' => (\Carbon\Carbon::now()->month >= 8) ? \Carbon\Carbon::parse(\Carbon\Carbon::now()->year.'-08-01') : \Carbon\Carbon::parse(\Carbon\Carbon::now()->subYear()->year.'-08-01'),

    'startRecurringThemeWeeksBefore' => env('startRecurringThemeWeeksBefore', 2),

    //Monate
    'months' => [
        1 => 'Januar',
        2 => 'Februar',
        3 => 'März',
        4 => 'April',
        5 => 'Mai',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'August',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Dezember',
    ],

    //Tage
    'days' => [
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
    ],
];
