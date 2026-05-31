<?php

namespace App\Policies;

use App\Models\ProcedureTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcedureTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool    { return $user->can('manage procedures'); }
    public function view(User $user, ProcedureTemplate $t): bool   { return $user->can('manage procedures'); }
    public function create(User $user): bool     { return $user->can('manage procedures'); }
    public function update(User $user, ProcedureTemplate $t): bool { return $user->can('manage procedures'); }
    public function clone(User $user, ProcedureTemplate $t): bool  { return $user->can('manage procedures'); }
    public function delete(User $user, ProcedureTemplate $t): bool { return $user->can('delete procedures'); }
    public function start(User $user, ProcedureTemplate $t): bool  { return $user->can('manage procedures'); }
}

