<?php

namespace App\Policies;

use App\Models\DiagnosticSession;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DiagnosticPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any diagnostics.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view diagnostics');
    }

    /**
     * Determine whether the user can view the diagnostic session.
     */
    public function view(User $user, DiagnosticSession $session): bool
    {
        if (!$user->hasPermissionTo('view diagnostics')) {
            return false;
        }

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        $schueler = $session->schueler;
        if (!$schueler || !$schueler->klasse_id) {
            return false;
        }

        return $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists();
    }

    /**
     * Determine whether the user can create diagnostic sessions.
     */
    public function create(User $user, Schueler $schueler): bool
    {
        if (!$user->hasPermissionTo('view diagnostics')) {
            return false;
        }

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        if (!$schueler->klasse_id) {
            return false;
        }

        return $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists();
    }

    /**
     * Determine whether the user can update the diagnostic session.
     */
    public function update(User $user, DiagnosticSession $session): bool
    {
        if (!$user->hasPermissionTo('view diagnostics')) {
            return false;
        }

        // Abgeschlossene Sessions können nicht bearbeitet werden
        if ($session->is_completed) {
            return false;
        }

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        $schueler = $session->schueler;
        if (!$schueler || !$schueler->klasse_id) {
            return false;
        }

        return $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists();
    }

    /**
     * Determine whether the user can complete the diagnostic session.
     */
    public function complete(User $user, DiagnosticSession $session): bool
    {
        if (!$user->hasPermissionTo('view diagnostics')) {
            return false;
        }

        // Bereits abgeschlossene Sessions können nicht nochmal abgeschlossen werden
        if ($session->is_completed) {
            return false;
        }

        // Prüfen ob User Zugriff auf die Klasse des Schülers hat
        $schueler = $session->schueler;
        if (!$schueler || !$schueler->klasse_id) {
            return false;
        }

        return $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->exists();
    }

    /**
     * Determine whether the user can reopen the diagnostic session.
     * Nur Admins mit manage diagnostics Permission
     */
    public function reopen(User $user, DiagnosticSession $session): bool
    {
        return $user->hasPermissionTo('manage diagnostics');
    }

    /**
     * Determine whether the user can delete the diagnostic session.
     */
    public function delete(User $user, DiagnosticSession $session): bool
    {
        return $user->hasPermissionTo('manage diagnostics');
    }
}

