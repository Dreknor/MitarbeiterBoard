#!/bin/sh
# activate maintenance mode
php artisan down
# update source code
git pull
# update PHP dependencies
composer install --no-interaction --prefer-dist
# --no-interaction Do not ask any interactive question
# --no-dev  Disables installation of require-dev packages.
# --prefer-dist  Forces installation from package dist even for dev versions.
# update database
php artisan migrate --force
# --force  Required to run when in production.

#Cache clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue-Worker neustarten (lädt neuen Code, erneuert SMTP-Verbindung)
php artisan queue:restart

# stop maintenance mode
php artisan up
