<?php

namespace App\Policies;

use App\Models\PaedDiaryEntry;
use App\Models\User;

/**
 * API v1: Zugriff auf Einträge des Pädagogischen Tagebuchs.
 *
 * - Lesen: Zugriff auf die Klasse des Eintrags oder auf die aktuelle Klasse mindestens eines
 *   zugeordneten Schülers (Schuljahreswechsel). Wie in der Schülerakte gilt das auch für
 *   vertrauliche Einträge (dossier_only).
 * - Bearbeiten vertraulicher Einträge: nur Autor oder Benutzer mit
 *   "view confidential diary entries" / Rolle Admin.
 * - Löschen: nur Autor oder Benutzer mit Sonderrechten.
 */
class PaedDiaryPolicy
{
    public function view(User $user, PaedDiaryEntry $entry): bool
    {
        return $this->hasEntryClassAccess($user, $entry);
    }

    public function update(User $user, PaedDiaryEntry $entry): bool
    {
        return $this->view($user, $entry) && $this->viewConfidentialEntry($user, $entry);
    }

    public function delete(User $user, PaedDiaryEntry $entry): bool
    {
        if (!$this->update($user, $entry)) {
            return false;
        }

        return (int) $entry->user_id === (int) $user->id
            || $user->canAccessAllStudents()
            || $user->canViewConfidentialDiaryEntries();
    }

    /**
     * Darf der Benutzer diesen (ggf. vertraulichen) Eintrag lesen?
     */
    public function viewConfidentialEntry(User $user, PaedDiaryEntry $entry): bool
    {
        if (!$entry->dossier_only) {
            return true;
        }

        return (int) $entry->user_id === (int) $user->id || $user->canViewConfidentialDiaryEntries();
    }

    /**
     * Darf der Benutzer generell vertrauliche Einträge fremder Autoren lesen?
     */
    public function viewConfidential(User $user): bool
    {
        return $user->canViewConfidentialDiaryEntries();
    }

    private function hasEntryClassAccess(User $user, PaedDiaryEntry $entry): bool
    {
        try {
            if (!$user->hasPermissionTo('view paed diary')) {
                return false;
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }

        if ($user->hasPaedClassAccess($entry->klasse_id)) {
            return true;
        }

        $classIds = $user->paedKlassenIds();
        if ($classIds->isEmpty()) {
            return false;
        }

        return $entry->schueler()->whereIn('schueler.klasse_id', $classIds)->exists();
    }
}
