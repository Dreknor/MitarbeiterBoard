<?php

namespace App\Policies;

use App\Models\Procedure_Step;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcedureStepPolicy
{
    use HandlesAuthorization;

    /** Wer den Prozess sehen darf, darf auch dessen Schritte (inkl. Kommentare/Verlauf) sehen. */
    public function view(User $user, Procedure_Step $step): bool
    {
        return $step->procedure !== null && $user->can('view', $step->procedure);
    }

    public function update(User $user, Procedure_Step $step): bool
    {
        return $user->can('manage procedures');
    }

    public function complete(User $user, Procedure_Step $step): bool
    {
        if ($user->can('manage procedures')) return true;
        if (!$user->can('complete own procedure steps')) return false;
        return $step->users->contains('id', $user->id);
    }

    public function assign(User $user, Procedure_Step $step): bool
    {
        return $user->can('manage procedures');
    }

    public function comment(User $user, Procedure_Step $step): bool
    {
        if ($user->can('manage procedures')) return true;
        if (!$user->can('comment procedure steps')) return false;
        // Nur Zugewiesene dürfen kommentieren (§8.3)
        return $step->users->contains('id', $user->id);
    }
}

