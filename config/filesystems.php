<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        'upload' => [
            'driver' => 'local',
            'root' => storage_path('app/upload'),
            'visibility' => 'public',
        ],

        // Private Ablage für Ticket-Anhänge (nicht über /storage erreichbar,
        // Auslieferung nur über TicketController::file mit Berechtigungsprüfung)
        // Zwischenspeicher für Wochenplan-Arbeitsblätter (bis zum Upload in die
        // Nextcloud bzw. dauerhaft, wenn die Nextcloud-Ablage aus ist). Auslieferung
        // nur über die Route wp.dateien.show.
        'wochenplan' => [
            'driver' => 'local',
            'root' => storage_path('app/wochenplan'),
            'visibility' => 'private',
        ],

        'tickets' => [
            'driver' => 'local',
            'root' => storage_path('app/tickets'),
            'visibility' => 'private',
        ],

        // Private Ablage für Anhänge an Ergebnisboard-Karten (Auslieferung nur über
        // ErgebnisboardController::datei mit Berechtigungsprüfung)
        'ergebnisboards' => [
            'driver' => 'local',
            'root' => storage_path('app/ergebnisboards'),
            'visibility' => 'private',
        ],

        // Private Ablage für Wiki-Anhänge (Bilder und Dateien; Auslieferung nur über
        // WikiAnhangController::show mit Prüfung über die WikiSeitePolicy)
        'wiki' => [
            'driver' => 'local',
            'root' => storage_path('app/wiki'),
            'visibility' => 'private',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
