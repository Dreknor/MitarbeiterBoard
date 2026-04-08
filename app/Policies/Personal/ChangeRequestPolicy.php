<?php

namespace App\Policies\Personal;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChangeRequestPolicy
{
    use HandlesAuthorization;

    public function view(User $user, $request): bool
    {
        // Eigener Antrag
        if ($request->employe_id === $user->id) return true;
        // Vorgesetzter oder Personalleitung
        return (isset($request->employe->superior_id) && $request->employe->superior_id === $user->id)
            || $user->can('edit personal_data:all');
    }

    public function create(User $user): bool
    {
        return true; // Jeder eingeloggte Mitarbeiter kann Änderungsanträge stellen
    }

    public function decide(User $user, $request): bool
    {
        return (isset($request->employe->superior_id) && $request->employe->superior_id === $user->id)
            || $user->can('edit personal_data:all');
    }

    public function update(User $user, $request): bool
    {
        return $this->decide($user, $request);
    }

    public function delete(User $user, $request): bool
    {
        return $user->can('edit personal_data:all');
    }
}

