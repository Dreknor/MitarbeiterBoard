<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentTerminated;
use App\Services\Personal\PersonalReminderService;

/**
 * Erstellt die DSGVO-Wiedervorlage (Aufbewahrungsfrist) beim endgültigen Ausscheiden.
 */
class CreateRetentionReminders
{
    public function __construct(private readonly PersonalReminderService $reminders) {}

    public function handle(EmploymentTerminated $event): void
    {
        $employment = $event->employment;
        $this->reminders->syncForEmployment($employment); // schließt Probezeit-/Ende-Erinnerungen
        $this->reminders->createRetention($employment->employe, $employment);
    }
}
