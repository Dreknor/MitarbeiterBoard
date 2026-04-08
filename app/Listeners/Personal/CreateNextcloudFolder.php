<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentCreated;

/**
 * Erstellt Nextcloud-Ordnerstruktur für neue Anstellung.
 * Implementierung folgt in Phase 2 (P2-01).
 */
class CreateNextcloudFolder
{
    public function handle(EmploymentCreated $event): void
    {
        // Stub – Implementierung folgt in Phase 2
    }
}

