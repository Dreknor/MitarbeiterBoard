<?php

namespace App\Observers\Personal;

use App\Models\personal\Employment;

/**
 * Observer für Employment-Model-Lifecycle.
 * Implementierung folgt in Phase 1 (P1-03).
 */
class EmploymentObserver
{
    public function created(Employment $employment): void
    {
        // Stub – Implementierung folgt in Phase 1
    }

    public function updated(Employment $employment): void
    {
        // Stub – Implementierung folgt in Phase 1
    }

    public function deleted(Employment $employment): void
    {
        // Stub – Implementierung folgt in Phase 1
    }
}

