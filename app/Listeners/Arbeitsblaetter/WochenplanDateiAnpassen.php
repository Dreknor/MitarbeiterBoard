<?php

namespace App\Listeners\Arbeitsblaetter;

use App\Events\Wochenplan\WpDateiAngelegt;
use App\Services\Arbeitsblaetter\AbWochenplanService;

/** Neues Arbeitsblatt am Klassenplan → bei Profilkindern automatisch erkennen lassen. */
class WochenplanDateiAnpassen
{
    public function handle(WpDateiAngelegt $event): void
    {
        app(AbWochenplanService::class)->dateiAngelegt($event->datei);
    }
}
