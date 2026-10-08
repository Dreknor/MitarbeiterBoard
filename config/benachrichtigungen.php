<?php

/*
|--------------------------------------------------------------------------
| Benachrichtigungen
|--------------------------------------------------------------------------
|
| Zentrale Definition der Benachrichtigungs-Kategorien. Jede Notification,
| die von App\Notifications\Benachrichtigung erbt, gehört zu genau einer
| Kategorie. Pro Kategorie entscheidet jede Person selbst über Push und
| Mail (sofort / zusammenfassung / aus). Fehlt eine Einstellung, gelten
| die Standardwerte von hier.
|
| 'permission': Kategorie erscheint nur in den Einstellungen, wenn die
|               Person mindestens eine der Permissions hat (null = alle).
|
*/

return [

    'mail_modi' => [
        'sofort'          => 'sofort',
        'zusammenfassung' => 'Zusammenfassung',
        'aus'             => 'aus',
    ],

    'kategorien' => [
        'vertretungen' => [
            'label'        => 'Vertretungen',
            'beschreibung' => 'Neue, geänderte oder gestrichene Vertretungen für Sie',
            'icon'         => 'fa-exchange-alt',
            'push'         => true,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'aufgaben' => [
            'label'        => 'Aufgaben',
            'beschreibung' => 'Ihnen zugewiesene Aufgaben aus Themen und Meetings',
            'icon'         => 'fa-tasks',
            'push'         => true,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'themen' => [
            'label'        => 'Themen & Protokolle',
            'beschreibung' => 'Neue Themen in abonnierten Gruppen, Zuweisungen und neue Protokolle',
            'icon'         => 'fa-comments',
            'push'         => false,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'meetings' => [
            'label'        => 'Meetings',
            'beschreibung' => 'Einladungen und Änderungen an Meetings',
            'icon'         => 'fa-users',
            'push'         => false,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'tickets' => [
            'label'        => 'Tickets',
            'beschreibung' => 'Neue Tickets, Kommentare und Statusänderungen',
            'icon'         => 'fa-ticket-alt',
            'push'         => true,
            'mail'         => 'sofort',
            'permission'   => ['view tickets'],
        ],
        'prozesse' => [
            'label'        => 'Prozesse',
            'beschreibung' => 'Ihnen zugewiesene Prozessschritte und Kommentare',
            'icon'         => 'fa-project-diagram',
            'push'         => false,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'terminlisten' => [
            'label'        => 'Terminlisten',
            'beschreibung' => 'Eintragungen und Absagen in Terminlisten',
            'icon'         => 'fa-list-alt',
            'push'         => false,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'abwesenheiten' => [
            'label'        => 'Abwesenheiten',
            'beschreibung' => 'Sofortmeldung, sobald eine neue Abwesenheit eingetragen wird (nur wenn Push oder Mail eingeschaltet ist). Den täglichen Überblick gibt es in der Tagesübersicht.',
            'icon'         => 'fa-user-clock',
            'push'         => false,
            'mail'         => 'aus',
            'permission'   => ['view absences'],
        ],
        'urlaub' => [
            'label'        => 'Urlaub',
            'beschreibung' => 'Urlaubsanträge, Genehmigungen und Ablehnungen',
            'icon'         => 'fa-umbrella-beach',
            'push'         => true,
            'mail'         => 'sofort',
            'permission'   => ['has holidays', 'approve holidays'],
        ],
        'dienstplan' => [
            'label'        => 'Dienstplan',
            'beschreibung' => 'Veröffentlichte und geänderte Dienstpläne',
            'icon'         => 'fa-calendar-week',
            'push'         => true,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'zeiterfassung' => [
            'label'        => 'Zeiterfassung',
            'beschreibung' => 'Arbeitszeitnachweise und Hinweise zur Zeiterfassung',
            'icon'         => 'fa-clock',
            'push'         => true,
            'mail'         => 'sofort',
            'permission'   => ['has timesheet'],
        ],
        'personal' => [
            'label'        => 'Personalverwaltung',
            'beschreibung' => 'Ablaufende Dokumente, Qualifikationen und Wiedervorlagen',
            'icon'         => 'fa-id-card',
            'push'         => false,
            'mail'         => 'sofort',
            'permission'   => ['edit employe'],
        ],
        'nachrichten' => [
            'label'        => 'Nachrichten',
            'beschreibung' => 'Neue Nachrichten im Board (abendliche Sammelmail)',
            'icon'         => 'fa-newspaper',
            'push'         => false,
            'mail'         => 'sofort',
            'permission'   => null,
        ],
        'system' => [
            'label'        => 'System',
            'beschreibung' => 'Technische Hinweise, z. B. Fehler bei Synchronisation oder Mailversand',
            'icon'         => 'fa-cog',
            'push'         => true,
            // Admin-Warnungen (z. B. Kalender-Sync) kamen bisher per Mail – Standard beibehalten
            'mail'         => 'sofort',
            'permission'   => null,
        ],
    ],

    /*
    | Nachmittags-Zusammenfassung (Kategorien mit mail = zusammenfassung)
    */
    'zusammenfassung' => [
        'uhrzeit' => '16:00',
    ],

    /*
    | Gelesene Benachrichtigungen werden nach dieser Anzahl Tage gelöscht.
    */
    'aufbewahrung_tage' => 180,

    /*
    | Tagesübersicht („Dein Tag“) – morgens oder am Vorabend.
    */
    'tagesvorschau' => [
        'standard_uhrzeit' => '06:30',
        'fenster' => [
            'morgens'  => ['05:00', '10:00'],
            'vorabend' => ['16:00', '22:00'],
        ],
        // Reihenfolge = Reihenfolge in Mail und Web-Ansicht
        'quellen' => [
            \App\Services\Benachrichtigungen\Tagesvorschau\VertretungenQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\DienstplanQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\MeetingsQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\KalenderQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\AufgabenQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\ProzesseQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\TicketsQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\GenehmigungenQuelle::class,
            \App\Services\Benachrichtigungen\Tagesvorschau\AbwesenheitenQuelle::class,
        ],
    ],

    /*
    | Titel der Wiki-Seite mit der Erklärung für Mitarbeitende.
    */
    'wiki_titel' => 'Benachrichtigungen',
];
