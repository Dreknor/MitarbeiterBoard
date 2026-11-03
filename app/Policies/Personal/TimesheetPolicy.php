<?php

namespace App\Policies\Personal;

use App\Models\personal\Timesheet;
use App\Models\User;
use App\Services\Personal\Zeit\ZeitZugriff;

/**
 * Arbeitszeitnachweise.
 *
 * Status: offen → eingereicht (Mitarbeiter) → abgeschlossen/gesperrt (Vorgesetzte/Personal).
 * Eingereichte Nachweise kann nur noch die prüfende Person ändern oder zurückgeben.
 */
class TimesheetPolicy
{
    public function __construct(private readonly ZeitZugriff $zugriff)
    {
    }

    public function viewEmploye(User $user, User $employe): bool
    {
        return $this->zugriff->darfNachweiseSehen($user, $employe);
    }

    public function view(User $user, Timesheet $timesheet): bool
    {
        return $timesheet->employe !== null && $this->viewEmploye($user, $timesheet->employe);
    }

    public function edit(User $user, Timesheet $timesheet): bool
    {
        if ($timesheet->is_locked || $timesheet->employe === null) {
            return false;
        }

        if ($this->zugriff->verwaltetNachweiseVon($user, $timesheet->employe)) {
            return true;
        }

        return $user->id === $timesheet->employe_id && $timesheet->submitted_at === null;
    }

    public function submit(User $user, Timesheet $timesheet): bool
    {
        return $user->id === $timesheet->employe_id
            && !$timesheet->is_locked
            && $timesheet->submitted_at === null
            && $timesheet->monthEnd()->isPast();
    }

    public function lock(User $user, Timesheet $timesheet): bool
    {
        return !$timesheet->is_locked
            && $timesheet->employe !== null
            && $this->zugriff->verwaltetNachweiseVon($user, $timesheet->employe);
    }

    public function returnToEmploye(User $user, Timesheet $timesheet): bool
    {
        return $timesheet->submitted_at !== null && $this->lock($user, $timesheet);
    }

    public function unlock(User $user, Timesheet $timesheet): bool
    {
        return $timesheet->is_locked && $user->can('edit employe') && $user->id !== $timesheet->employe_id;
    }
}
