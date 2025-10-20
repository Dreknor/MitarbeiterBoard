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
        return $session->user_id === $user->id ||
               $user->paed_klassen()->where('klassen.id', $session->klasse_id)->exists();
    }

    /**
     * Bestimmt ob der Benutzer die Session bearbeiten kann
     */
    public function update(User $user, GradingDocumentationSession $session)
    {
        // Nur der Ersteller kann die Session bearbeiten
        return $session->user_id === $user->id;
    }
}

