<?php

/*
|--------------------------------------------------------------------------
| Online-Updater
|--------------------------------------------------------------------------
|
| Konfiguration für den Updater unter /updater (Permission "make updates").
| Das Update läuft als Hintergrundprozess `php artisan app:update` und
| entspricht inhaltlich deploy.sh (git pull → composer → build → migrate →
| Caches leeren → Queue neu starten).
|
*/

return [

    // Arbeitsverzeichnis (Git-Checkout der Anwendung)
    'path' => base_path(),

    // Git-Remote und Branch, von dem aktualisiert wird (null = aktueller Branch)
    'remote' => env('UPDATER_REMOTE', 'origin'),
    'branch' => env('UPDATER_BRANCH'),

    // Pfade zu den Programmen (null = automatisch über PATH suchen)
    'php_binary' => env('UPDATER_PHP_BINARY'),
    'git_binary' => env('UPDATER_GIT_BINARY', 'git'),
    'composer_binary' => env('UPDATER_COMPOSER_BINARY', 'composer'),
    'npm_binary' => env('UPDATER_NPM_BINARY', 'npm'),

    // composer install ausführen?
    'composer' => env('UPDATER_COMPOSER', true),
    'composer_args' => ['install', '--no-interaction', '--prefer-dist', '--no-dev', '--optimize-autoloader'],

    // Frontend neu bauen? public/build ist nicht im Repository.
    // npm ci läuft nur, wenn sich package-lock.json geändert hat.
    'npm_build' => env('UPDATER_NPM_BUILD', true),

    // Wartungsmodus während des Updates
    'maintenance_mode' => env('UPDATER_MAINTENANCE_MODE', true),

    // Artisan-Befehle nach dem Code-Update (laufen als eigener Prozess mit dem neuen Code)
    'artisan_commands' => [
        ['migrate', '--force'],
        ['cache:clear'],
        ['config:clear'],
        ['route:clear'],
        ['view:clear'],
        ['queue:restart'],
    ],

    // Zeitlimit pro Schritt in Sekunden
    'timeout' => env('UPDATER_TIMEOUT', 900),

    // Ablage für Status, Log und Lock
    'storage_path' => storage_path('app/updater'),

];
