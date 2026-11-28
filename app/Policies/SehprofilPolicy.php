<?php

namespace App\Policies;

use App\Models\Arbeitsblaetter\Sehprofil;
use App\Models\User;

/**
 * Sehprofile pflegen nur Personen mit `manage visual profiles`; anzeigen und für die
 * Ausgabe auswählen alle mit `create adapted worksheets`.
 */
class SehprofilPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->recht($user, 'create adapted worksheets') || $this->recht($user, 'manage visual profiles');
    }

    public function view(User $user, Sehprofil $profil): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $this->recht($user, 'manage visual profiles');
    }

    public function update(User $user, Sehprofil $profil): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Sehprofil $profil): bool
    {
        return $this->manage($user);
    }

    private function recht(User $user, string $recht): bool
    {
        try {
            return $user->hasPermissionTo($recht);
        } catch (\Throwable) {
            return false;
        }
    }
}
