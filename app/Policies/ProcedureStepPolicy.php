<?php

namespace App\Policies;

use App\Models\Procedure_Step;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcedureStepPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Procedure_Step $step): bool
    {
        if ($user->can('manage procedures')) return true;
        if (!$user->can('view assigned procedures')) return false;

        if ($step->users()->where('users.id', $user->id)->exists()) return true;
        if (($user->position_id ?? null) === $step->position_id) return true;
        return false;
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

