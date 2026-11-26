<?php

namespace App\Console\Commands\Wochenplan;

use App\Services\Wochenplan\WpAblageService;
use Illuminate\Console\Command;

/** Prüft, ob die abgelegten Arbeitsblätter noch in der Nextcloud vorhanden sind (läuft nächtlich). */
class DateienPruefen extends Command
{
    protected $signature = 'wochenplan:dateien-pruefen';

    protected $description = 'Markiert Wochenplan-Arbeitsblätter, die in der Nextcloud fehlen';

    public function handle(WpAblageService $ablage): int
    {
        $ergebnis = $ablage->dateienPruefen();
        $this->info("Geprüft: {$ergebnis['geprueft']}, fehlend: {$ergebnis['fehlt']}, wieder vorhanden: {$ergebnis['wieder_da']}");

        return self::SUCCESS;
    }
}
