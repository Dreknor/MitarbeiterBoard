<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentCreated;
use App\Services\Personal\PersonalReminderService;

/**
 * Legt Wiedervorlagen für Probezeitende und Ende einer Befristung an.
 * (Änderungen an diesen Daten pflegt der EmploymentObserver nach.)
 */
class CreateProbationReminder
{
    public function __construct(private readonly PersonalReminderService $reminders) {}

    public function handle(EmploymentCreated $event): void
    {
        $this->reminders->syncForEmployment($event->employment);
        // Erneute Beschäftigung hebt offene Aufbewahrungsfristen auf
        $this->reminders->cancelRetention($event->employment->employe);
    }
}
