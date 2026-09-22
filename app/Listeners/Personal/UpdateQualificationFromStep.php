<?php

namespace App\Listeners\Personal;

use App\Events\Personal\ProcedureStepCompleted;

/**
 * Aktualisiert Qualifikationen wenn ein Prozessschritt abgeschlossen wird.
 * Implementierung folgt in Phase 3 (P3-01).
 */
class UpdateQualificationFromStep
{
    public function handle(ProcedureStepCompleted $event): void
    {
        // Stub – Implementierung folgt in Phase 3
    }
}

