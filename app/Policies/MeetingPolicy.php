<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\Theme;
use App\Models\User;

/**
 * Zugriffsregeln für Meetings (gruppengebunden und frei).
 *
 * - Sehen & Mitarbeiten (Themen, Protokolle, Rollen): alle Teilnehmer –
 *   Gruppenmitglieder, eingeladene Personen, Mitglieder eingeladener
 *   Gruppen/Bereiche, Personen eingeladener Rollen sowie der Ersteller.
 * - Verwalten (bearbeiten, absagen, löschen, Teilnehmer, Einladungen):
 *   bei Gruppen-Meetings alle Gruppenmitglieder, sonst Ersteller und
 *   als Organisator eingeladene Personen.
 */
class MeetingPolicy
{
    public function create(User $user): bool
    {
        return $user->can('create free meetings')
            || $user->groups_rel()->where('use_meetings', true)->exists();
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $meeting->hasParticipant($user);
    }

    public function contribute(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }

    public function manage(User $user, Meeting $meeting): bool
    {
        if ($meeting->isOrganizer($user)) {
            return true;
        }

        return $meeting->group_id !== null
            && $user->groups()->contains('id', $meeting->group_id);
    }

    /**
     * Ein Thema ist im Meeting-Kontext sichtbar, wenn es dem Meeting zugeordnet ist.
     */
    public function viewTheme(User $user, Meeting $meeting, Theme $theme): bool
    {
        return $this->view($user, $meeting)
            && $meeting->themes()->whereKey($theme->id)->exists();
    }
}
