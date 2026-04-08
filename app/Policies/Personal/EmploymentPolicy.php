<?php

namespace App\Policies\Personal;

use App\Models\personal\Employment;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy für Employment-Modell.
 * Prüft objekt-bezogene Berechtigung: Darf User X *diese* Anstellung sehen/bearbeiten?
 *
 * Vollständige Implementierung folgt in Phase 1 (P1-01).
 */
class EmploymentPolicy
{
    use HandlesAuthorization;

    /**
     * Darf der User die Anstellung ansehen?
     */
    public function view(User $user, Employment $employment): bool
    {
        if ($employment->employe_id === $user->id) return true;
        return $user->can('view contracts')
            && app(PersonalScopeService::class)->canAccess($user, $employment->employe);
    }

    /**
     * Darf der User die Anstellung bearbeiten?
     */
    public function update(User $user, Employment $employment): bool
    {
        return $user->can('edit contracts')
            && app(PersonalScopeService::class)->canAccess($user, $employment->employe, 'edit');
    }

    /**
     * Darf der User eine neue Anstellung erstellen?
     */
    public function create(User $user): bool
    {
        return $user->can('edit contracts');
    }

    /**
     * Darf der User die Anstellung löschen?
     */
    public function delete(User $user, Employment $employment): bool
    {
        return $user->can('edit contracts')
            && app(PersonalScopeService::class)->canAccess($user, $employment->employe, 'edit');
    }

    /**
     * Darf der User die Gehaltsdaten sehen?
     */
    public function viewSalary(User $user, Employment $employment): bool
    {
        return $user->can('view salary')
            && app(PersonalScopeService::class)->canAccess($user, $employment->employe);
    }

    /**
     * Darf der User die Gehaltsdaten bearbeiten?
     */
    public function editSalary(User $user, Employment $employment): bool
    {
        return $user->can('edit salary');
    }
}
