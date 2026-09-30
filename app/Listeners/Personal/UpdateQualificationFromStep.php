<?php

namespace App\Listeners\Personal;

use App\Events\Personal\ProcedureStepCompleted;
use App\Services\Personal\PersonalProcessService;

/**
 * Meldet erledigte Prozessschritte an die Personalverwaltung zurück
 * (Abschluss von On-/Offboarding, Qualifikation aus Schrittname).
 */
class UpdateQualificationFromStep
{
    public function __construct(private readonly PersonalProcessService $processes) {}

    public function handle(ProcedureStepCompleted $event): void
    {
        $this->processes->handleStepCompleted($event->procedureId, $event->stepId, $event->userId);
    }
}
