<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentCreated;

/**
 * Initialisiert fehlende Pflicht-Qualifikationen für neue Anstellung.
 * Implementierung folgt in Phase 2 (P2-03).
 */
class InitMissingQualifications
{
    public function handle(EmploymentCreated $event): void
    {
        // Stub – Implementierung folgt in Phase 2
    }
}

