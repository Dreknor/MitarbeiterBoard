<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmployeeNameChanged;

/**
 * Benennt Nextcloud-Ordner um wenn sich der Name eines Mitarbeiters ändert.
 * Implementierung folgt in Phase 2 (P2-01).
 */
class RenameNextcloudFolder
{
    public function handle(EmployeeNameChanged $event): void
    {
        // Stub – Implementierung folgt in Phase 2
    }
}

