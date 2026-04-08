<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentTerminated;

/**
 * Startet Offboarding-Prozess bei Anstellungsende.
 * Implementierung folgt in Phase 3 (P3-01).
 */
class StartOffboardingProcess
{
    public function handle(EmploymentTerminated $event): void
    {
        // Stub – Implementierung folgt in Phase 3
    }
}

