<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentStatusChanged;

/**
 * Invalidiert den Scope-Cache wenn sich ein Employment-Status ändert.
 * Implementierung folgt in Phase 1 (P1-01).
 */
class InvalidateScopeCache
{
    public function handle(EmploymentStatusChanged $event): void
    {
        // Stub – Implementierung folgt in Phase 1
    }
}

