<?php

namespace App\Listeners\Personal;

use App\Events\Personal\EmploymentStatusChanged;
use App\Services\Personal\PersonalScopeService;

/**
 * Invalidiert den Scope-Cache wenn sich ein Employment-Status ändert.
 */
class InvalidateScopeCache
{
    public function handle(EmploymentStatusChanged $event): void
    {
        app(PersonalScopeService::class)->invalidateCache($event->employment->employe);
    }
}
