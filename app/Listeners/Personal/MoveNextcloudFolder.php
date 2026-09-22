<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentTerminated;

/**
 * Verschiebt Nextcloud-Ordner bei Anstellungsende (Angestellt → Ausgeschieden).
 * Implementierung folgt in Phase 2 (P2-01).
 */
class MoveNextcloudFolder
{
    public function handle(EmploymentTerminated $event): void
    {
        // Stub – Implementierung folgt in Phase 2
    }
}

