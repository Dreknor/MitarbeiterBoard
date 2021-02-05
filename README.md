<p align="center"><img src="https://mitarbeiter.esz-radebeul.de/img/logo.png" width="400"></p>

## Über das MitarbeiterBoard

Das MitarbeiterBoard ist ein Freizeitprojekt, welches entstanden ist um die Kommunikation innerhalb des Evangelischen Schulzentrums zu unterstützen. Es ermöglicht die Vorbereitung von Dienstberatungen online, in dem Themen durch Leitungen und Mitarbeiter im vorraus benannt, terminiert und priorisiert werden. Die Protokolle der besprochenen Themen werden direkt zu dem Thema abgelegt und sind somit jederzeit direkt abruf- und nachverfolgbar.
Es basiert auf dem [Laravel-Framework](https://laravel.com/).

## Nutzung

Obwohl das MitarbeiterBoard ausschließlich für das Evangelische Schulzentrum Radebeul gedacht war, kann die Software frei für nicht-kommerzielle Projekte im Bereich der Bildung genutzt werden. Es gibt jedoch keinerlei Anspruch auf Support oder Haftung, sollten Schäden oder Probleme auftreten.
Änderungen und Weiterentwicklungen sind ebenfalls als Open-Source zur Verfügung zu stellen.

## Installation

Nach dem Upload der Dateien auf den Server ist zunächst die Datei ".env.example" in ".env" umzubenennen und auszufüllen. Entscheidend sind dabei die Eintragungen zu Datenbank und Mail-Server.

Anschließend die Installation durchführen:

```bash
composer install
```
```bash
php artisan key:generate

```bash
php artisan webpush:vapid
```

```bash
php artisan migrate
```
Während dem Erstellen der Datenbanktabellen wird ein erster Benutzer mit der in der .env-Datei angegebenen E-Mail erstellt. Als Kennwort dient das aktuelle Datum 8-stellig. Es muss mit dem ersten Login geändert werden.

Nun muss noch der CronJob angelegt werden, damit die automatisierten Prozesse für Benachrichtigungen und Mail-Versan laufen:

```bash
crontab -e
```

und dort eintragen:
```bash
* * * * * cd /your-project-path && php artisan schedule:run >> /dev/null 2>&1
```
