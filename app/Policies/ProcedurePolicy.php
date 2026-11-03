<?php

namespace App\Policies;

use App\Models\Procedure;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy für laufende Prozesse und Vorlagen-Verwaltung (§5.1).
 *
 * Berechtigungsmatrix:
 *  - `manage procedures` → vollständige Verwaltung (view/create/update/delete/start/end).
 *  - `view assigned procedures` → nur zugewiesene Prozesse sehen.
 *  - `delete procedures` zusätzlich nötig für `delete`.
 */
class ProcedurePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('manage procedures') || $user->can('view assigned procedures');
    }

    public function view(User $user, Procedure $procedure): bool
    {
        return Procedure::whereKey($procedure->getKey())->sichtbarFuer($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('manage procedures');
    }

    public function update(User $user, Procedure $procedure): bool
    {
        return $user->can('manage procedures');
    }

    public function start(User $user, Procedure $procedure): bool
    {
        return $user->can('manage procedures');
    }

    public function end(User $user, Procedure $procedure): bool
    {
        return $user->can('manage procedures');
    }

    public function delete(User $user, Procedure $procedure): bool
    {
        return $user->can('delete procedures');
    }
}

