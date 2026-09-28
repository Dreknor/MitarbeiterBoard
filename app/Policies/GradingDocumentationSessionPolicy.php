<?php

namespace App\Policies;

use App\Models\GradingDocumentationSession;
use App\Models\User;

class GradingDocumentationSessionPolicy
{
    /**
     * Bestimmt ob der Benutzer die Session ansehen kann
     */
    public function view(User $user, GradingDocumentationSession $session)
    {
        // Benutzer muss der Ersteller der Session sein oder Zugriff auf die Klasse haben
        return (int) $session->user_id === (int) $user->id ||
               $user->canAccessAllStudents() ||
               $user->paed_klassen()->where('klassen.id', $session->klasse_id)->exists();
    }

    /**
     * Bestimmt ob der Benutzer die Session bearbeiten kann
     */
    public function update(User $user, GradingDocumentationSession $session)
    {
        // Nur der Ersteller kann die Session bearbeiten
        return (int) $session->user_id === (int) $user->id;
    }
}

