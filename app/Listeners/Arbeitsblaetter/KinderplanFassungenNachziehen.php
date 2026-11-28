<?php

namespace App\Listeners\Arbeitsblaetter;

use App\Events\Wochenplan\WpKinderplanAngelegt;
use App\Services\Arbeitsblaetter\AbWochenplanService;

/** Später angelegter Kinderplan → fehlende Fassungen bereits freigegebener Blätter erzeugen. */
class KinderplanFassungenNachziehen
{
    public function handle(WpKinderplanAngelegt $event): void
    {
        app(AbWochenplanService::class)->kinderplanAngelegt($event->kinderplan);
    }
}
