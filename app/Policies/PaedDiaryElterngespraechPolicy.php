<?php

namespace App\Policies;

use App\Models\PaedDiaryElterngespraech;
use App\Models\Schueler;
use App\Models\User;

/**
 * Zugriff auf Elterngespräche (Web und API v1).
 *
 * - Sehen: Zugriff auf den Schüler (StudentPolicy) und – bei vertraulichen Gesprächen – Ersteller,
 *   eingetragene Kolleg:innen oder "view confidential diary entries"/Admin.
 * - Bearbeiten, Abschließen, Absagen: nur solange das Gespräch geplant ist.
 * - Wieder öffnen: abgesagte alle Sichtberechtigten, abgeschlossene nur Ersteller oder Sonderrecht.
 * - Löschen: Ersteller oder Sonderrechte (wie PaedDiaryPolicy::delete).
 */
class PaedDiaryElterngespraechPolicy
{
    public function view(User $user, PaedDiaryElterngespraech $gespraech): bool
    {
        $schueler = $gespraech->schueler;

        return $schueler && $user->can('view', $schueler) && $gespraech->istSichtbarFuer($user);
    }

    public function create(User $user, Schueler $schueler): bool
    {
        return $user->can('update', $schueler);
    }

    public function update(User $user, PaedDiaryElterngespraech $gespraech): bool
    {
        return $gespraech->istGeplant() && $this->view($user, $gespraech);
    }

    public function abschliessen(User $user, PaedDiaryElterngespraech $gespraech): bool
    {
        return $this->update($user, $gespraech);
    }

    public function absagen(User $user, PaedDiaryElterngespraech $gespraech): bool
    {
        return $this->update($user, $gespraech);
    }

    public function wiederOeffnen(User $user, PaedDiaryElterngespraech $gespraech): bool
    {
        if ($gespraech->istGeplant() || !$this->view($user, $gespraech)) {
            return false;
        }
        if ($gespraech->status === PaedDiaryElterngespraech::STATUS_ABGESAGT) {
            return true;
        }

        return (int) $gespraech->user_id === (int) $user->id || $user->canViewConfidentialDiaryEntries();
    }

    public function delete(User $user, PaedDiaryElterngespraech $gespraech): bool
    {
        if (!$this->view($user, $gespraech)) {
            return false;
        }

        return (int) $gespraech->user_id === (int) $user->id
            || $user->canAccessAllStudents()
            || $user->canViewConfidentialDiaryEntries();
    }
}
