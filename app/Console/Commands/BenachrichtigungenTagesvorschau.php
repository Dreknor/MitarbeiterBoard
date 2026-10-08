<?php

namespace App\Console\Commands;

use App\Services\Benachrichtigungen\TagesvorschauService;
use Illuminate\Console\Command;

/**
 * Verschickt die Tagesübersicht „Dein Tag“ an alle Personen, deren gewählte
 * Uhrzeit (morgens oder am Vorabend) jetzt erreicht ist. Läuft alle 15 Minuten.
 */
class BenachrichtigungenTagesvorschau extends Command
{
    protected $signature = 'benachrichtigungen:tagesvorschau';

    protected $description = 'Verschickt fällige Tagesübersichten („Dein Tag“)';

    public function handle(TagesvorschauService $service): int
    {
        $anzahl = $service->versendeFaellige(now());

        $this->info("$anzahl Tagesübersicht(en) versendet.");

        return self::SUCCESS;
    }
}
