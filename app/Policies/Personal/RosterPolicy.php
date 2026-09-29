<?php

namespace App\Policies\Personal;

use App\Models\personal\Roster;
use App\Models\User;

/**
 * Dienstpläne: Planen nur mit "create roster" und für die eigenen Abteilungen
 * ("manage all rosters" hebt die Abteilungsgrenze auf).
 */
class RosterPolicy
{
    public function manageDepartment(User $user, $department): bool
    {
        if (!$user->can('create roster')) {
            return false;
        }

        if ($user->can('manage all rosters')) {
            return true;
        }

        $departmentId = is_object($department) ? $department->id : (int) $department;

        return $user->groups_rel->contains('id', $departmentId);
    }

    public function manage(User $user, Roster $roster): bool
    {
        return $this->manageDepartment($user, $roster->department_id);
    }

    public function view(User $user, Roster $roster): bool
    {
        if ($this->manage($user, $roster)) {
            return true;
        }

        if (!$roster->published || $roster->is_template) {
            return false;
        }

        return $user->groups_rel->contains('id', $roster->department_id)
            || $roster->working_times()->where('employe_id', $user->id)->exists()
            || $roster->events()->where('employe_id', $user->id)->exists();
    }
}
