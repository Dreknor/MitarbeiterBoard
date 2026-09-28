<?php

namespace App\Policies;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;

/**
 * API v1: Zugriff auf Schüler.
 *
 * Pädagogen dürfen Schüler der ihnen (klasse_user) zugeordneten Klassen einsehen und bearbeiten.
 * Benutzer mit "view all students" bzw. der Rolle Admin haben klassenübergreifenden Zugriff.
 */
class StudentPolicy
{
    public function view(User $user, Schueler $schueler): bool
    {
        return $this->hasModuleAccess($user) && $user->hasPaedClassAccess($schueler->klasse_id);
    }

    public function update(User $user, Schueler $schueler): bool
    {
        return $this->view($user, $schueler);
    }

    /** Diagnosedaten einsehen / erfassen (zusätzlich "view diagnostics") */
    public function viewDiagnostics(User $user, Schueler $schueler): bool
    {
        return $this->view($user, $schueler) && $this->safeCan($user, 'view diagnostics');
    }

    /** Graduierungsstufe vergeben (zusätzlich "manage grading systems", wie im Web-Frontend) */
    public function changeGradingStage(User $user, Schueler $schueler): bool
    {
        return $this->update($user, $schueler) && $this->safeCan($user, 'manage grading systems');
    }

    /** Zugriff auf eine Klasse (Klassenliste) */
    public function viewClass(User $user, Klasse $klasse): bool
    {
        return $this->hasModuleAccess($user) && $user->hasPaedClassAccess($klasse->id);
    }

    private function hasModuleAccess(User $user): bool
    {
        return $this->safeCan($user, 'view paed diary');
    }

    private function safeCan(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }
}
