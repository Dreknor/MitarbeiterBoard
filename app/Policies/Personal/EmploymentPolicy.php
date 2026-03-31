<?php

namespace App\Policies\Personal;

use App\Models\personal\Employment;
use App\Models\User;
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
        // Stub – wird in Phase 1 über PersonalScopeService implementiert
        return $user->can('view contracts');
    }

    /**
     * Darf der User die Anstellung bearbeiten?
     */
    public function update(User $user, Employment $employment): bool
    {
        // Stub – wird in Phase 1 über PersonalScopeService implementiert
        return $user->can('edit contracts');
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
        return $user->can('edit contracts');
    }

    /**
     * Darf der User die Gehaltsdaten sehen?
     */
    public function viewSalary(User $user, Employment $employment): bool
    {
        return $user->can('view salary');
    }

    /**
     * Darf der User die Gehaltsdaten bearbeiten?
     */
    public function editSalary(User $user, Employment $employment): bool
    {
        return $user->can('edit salary');
    }
}

