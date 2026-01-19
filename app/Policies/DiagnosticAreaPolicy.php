<?php

namespace App\Policies;

use App\Models\DiagnosticArea;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DiagnosticAreaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any areas.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage diagnostics');
    }

    /**
     * Determine whether the user can view the area.
     */
    public function view(User $user, DiagnosticArea $area): bool
    {
        return $user->hasPermissionTo('view diagnostics') || $user->hasPermissionTo('manage diagnostics');
    }

    /**
     * Determine whether the user can view a specific area (alias for view).
     * Used by export controllers.
     */
    public function viewArea(User $user, DiagnosticArea $area): bool
    {
        return $user->hasPermissionTo('view diagnostics') || $user->hasPermissionTo('manage diagnostics');
    }

    /**
     * Determine whether the user can create areas.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage diagnostics');
    }

    /**
     * Determine whether the user can update the area.
     */
    public function update(User $user, DiagnosticArea $area): bool
    {
        return $user->hasPermissionTo('manage diagnostics');
    }

    /**
     * Determine whether the user can delete the area.
     */
    public function delete(User $user, DiagnosticArea $area): bool
    {
        return $user->hasPermissionTo('manage diagnostics');
    }
}

