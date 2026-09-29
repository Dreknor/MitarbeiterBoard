<?php

namespace App\Policies\Personal;

use App\Models\personal\Holiday;
use App\Models\User;
use App\Services\Personal\Zeit\ZeitZugriff;

class HolidayPolicy
{
    public function __construct(private readonly ZeitZugriff $zugriff)
    {
    }

    public function createFor(User $user, User $employe): bool
    {
        return $this->zugriff->darfUrlaubErfassenFuer($user, $employe);
    }

    /**
     * Urlaub für alle/mehrere eintragen (z. B. Betriebsferien).
     */
    public function createForAll(User $user): bool
    {
        return $user->can('approve holidays') && $user->can('approve all holidays');
    }

    public function approve(User $user, Holiday $holiday): bool
    {
        return $holiday->employe !== null && $this->zugriff->darfUrlaubGenehmigen($user, $holiday->employe);
    }

    public function reject(User $user, Holiday $holiday): bool
    {
        return $this->approve($user, $holiday);
    }

    /**
     * Löschen: eigener, noch nicht entschiedener Antrag – oder Genehmigende (auch rückwirkend).
     */
    public function delete(User $user, Holiday $holiday): bool
    {
        if ($this->approve($user, $holiday)) {
            return true;
        }

        return $holiday->employe_id === $user->id && ($holiday->is_pending || $holiday->rejected);
    }

    /**
     * Stornierung eines genehmigten, noch nicht begonnenen Urlaubs beantragen.
     */
    public function requestCancellation(User $user, Holiday $holiday): bool
    {
        return $holiday->employe_id === $user->id
            && $holiday->approved
            && $holiday->cancellation_requested_at === null
            && $holiday->start_date->isFuture();
    }

    public function decideCancellation(User $user, Holiday $holiday): bool
    {
        return $holiday->cancellation_requested_at !== null && $this->approve($user, $holiday);
    }

    public function manageAccount(User $user, User $employe): bool
    {
        return $user->id !== $employe->id && ($user->can('edit employe') || ($user->can('approve all holidays') && $user->can('approve holidays')));
    }
}
