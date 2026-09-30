<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentCreated;
use App\Services\Personal\PersonalProcessService;

/**
 * Startet den Onboarding-Prozess (Vorlage aus den Einstellungen) bei der ersten Anstellung einer Person.
 */
class StartOnboardingProcess
{
    public function __construct(private readonly PersonalProcessService $processes) {}

    public function handle(EmploymentCreated $event): void
    {
        $this->processes->startOnboarding($event->employment);
    }
}
