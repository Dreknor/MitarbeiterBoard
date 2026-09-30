<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentTerminated;
use App\Services\Personal\PersonalProcessService;

/**
 * Startet den Offboarding-Prozess, wenn eine Person keine weitere laufende Anstellung hat.
 */
class StartOffboardingProcess
{
    public function __construct(private readonly PersonalProcessService $processes) {}

    public function handle(EmploymentTerminated $event): void
    {
        $this->processes->startOffboarding($event->employment);
    }
}
