<?php

namespace App\Policies;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\User;

/**
 * Angepasste Arbeitsblätter: Die Bibliothek ist schulintern für alle mit
 * `create adapted worksheets` sichtbar; bearbeiten/löschen dürfen die Ersteller:in
 * und Personen mit `manage adapted worksheets`.
 */
class AngepasstesBlattPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->recht($user, 'create adapted worksheets');
    }

    public function view(User $user, AngepasstesBlatt $blatt): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AngepasstesBlatt $blatt): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return (int) $blatt->erstellt_von === (int) $user->id || $this->recht($user, 'manage adapted worksheets');
    }

    public function delete(User $user, AngepasstesBlatt $blatt): bool
    {
        return $this->update($user, $blatt);
    }

    /** Fassungen ausgeben darf, wer das Blatt sehen darf – freigegeben oder als eigenes. */
    public function ausgeben(User $user, AngepasstesBlatt $blatt): bool
    {
        return $this->view($user, $blatt) && ($blatt->istFreigegeben() || $this->update($user, $blatt));
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
