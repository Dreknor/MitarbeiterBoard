<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentTerminated;

/**
 * Erstellt DSGVO-Aufbewahrungsfrist-Erinnerungen bei Anstellungsende.
 * Implementierung folgt in Phase 5 (P5-02).
 */
class CreateRetentionReminders
{
    public function handle(EmploymentTerminated $event): void
    {
        // Stub – Implementierung folgt in Phase 5
    }
}

