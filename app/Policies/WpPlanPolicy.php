<?php

namespace App\Policies;

use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpPlan;

/**
 * Zugriff auf Wochenpläne.
 *
 * - Lesen: `view wochenplan` oder `create wochenplan`, Schreiben: `create wochenplan`.
 * - Klassenpläne: Pädagog:innen der Klasse (`klasse_user`).
 * - Kinderpläne: über die **aktuelle** Klasse des Kindes.
 * - Pläne ohne Klasse (Altbestand): nur die erstellende Person.
 * - Vorlagen: alle lesen/benutzen, ändern/löschen nur die erstellende Person.
 * - `manage all wochenplaene` (und Admin): alles.
 */
class WpPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->kannLesen($user);
    }

    public function create(User $user): bool
    {
        return $this->kannSchreiben($user);
    }

    public function view(User $user, WpPlan $plan): bool
    {
        return $this->kannLesen($user) && ($plan->isVorlage() || $this->zugriff($user, $plan));
    }

    public function update(User $user, WpPlan $plan): bool
    {
        if (! $this->kannSchreiben($user)) {
            return false;
        }

        if ($plan->isVorlage()) {
            return WpPlan::darfAlles($user) || (int) $plan->created_by === (int) $user->id;
        }

        return $this->zugriff($user, $plan);
    }

    public function delete(User $user, WpPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    /** Aus einer Vorlage einen Plan erstellen bzw. eine Vorlage benutzen. */
    public function useTemplate(User $user, WpPlan $plan): bool
    {
        return $plan->isVorlage() && $this->kannSchreiben($user);
    }

    /** Status je Kind setzen (nur Pädagog:innen mit Zugriff auf den Plan). */
    public function trackProgress(User $user, WpPlan $plan): bool
    {
        return ! $plan->isVorlage() && $this->kannLesen($user) && $this->zugriff($user, $plan);
    }

    /** Darf die Person mit Plänen dieser Klasse arbeiten? */
    public static function darfKlasse(User $user, ?int $klasseId): bool
    {
        if (WpPlan::darfAlles($user)) {
            return true;
        }

        return $klasseId !== null && $user->paedKlassenIds()->contains($klasseId);
    }

    public static function darfSchueler(User $user, Schueler $schueler): bool
    {
        return self::darfKlasse($user, $schueler->klasse_id ? (int) $schueler->klasse_id : null);
    }

    private function zugriff(User $user, WpPlan $plan): bool
    {
        if (WpPlan::darfAlles($user)) {
            return true;
        }

        if ($plan->isSchuelerplan()) {
            return $plan->schueler !== null && self::darfSchueler($user, $plan->schueler);
        }

        if ($plan->klasse_id === null) {
            return (int) $plan->created_by === (int) $user->id;
        }

        return self::darfKlasse($user, (int) $plan->klasse_id);
    }

    private function kannLesen(User $user): bool
    {
        return $user->can('view wochenplan') || $user->can('create wochenplan');
    }

    private function kannSchreiben(User $user): bool
    {
        return $user->can('create wochenplan');
    }
}
