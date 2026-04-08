<?php

namespace App\Observers\Personal;

use App\Models\personal\Employment;
use App\Services\Personal\PersonalScopeService;

class EmploymentObserver
{
    public function created(Employment $employment): void
    {
        // Scope-Cache invalidieren nach neuer Anstellung
        app(PersonalScopeService::class)->invalidateCache($employment->employe);
    }

    public function updated(Employment $employment): void
    {
        // Scope-Cache invalidieren bei Änderungen (z.B. department_id)
        app(PersonalScopeService::class)->invalidateCache($employment->employe);
    }

    public function deleted(Employment $employment): void
    {
        app(PersonalScopeService::class)->invalidateCache($employment->employe);
    }
}
