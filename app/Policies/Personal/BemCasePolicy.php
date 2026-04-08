<?php

namespace App\Policies\Personal;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BemCasePolicy
{
    use HandlesAuthorization;

    public function view(User $user, $bemCase): bool
    {
        if (isset($bemCase->responsible_id) && $bemCase->responsible_id === $user->id) return true;
        return $user->can('manage bem') || $user->can('view bem');
    }

    public function create(User $user): bool
    {
        return $user->can('manage bem');
    }

    public function update(User $user, $bemCase): bool
    {
        return $user->can('manage bem');
    }

    public function delete(User $user, $bemCase): bool
    {
        return $user->can('manage bem');
    }
}

