<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Zugriffsregeln für das Ticketsystem.
 *
 * - Sehen & Kommentieren: Ersteller des Tickets sowie Bearbeiter (edit tickets).
 * - Verwalten (zuweisen, Kategorie/Priorität ändern, anpinnen, intern
 *   kommentieren, auf Warten setzen): nur Bearbeiter.
 * - Schließen & Wiedereröffnen: Bearbeiter und Ersteller.
 */
class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $this->isEditor($user) || $this->isOwner($user, $ticket);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return !$ticket->isClosed() && $this->view($user, $ticket);
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return $this->isEditor($user);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return !$ticket->isClosed() && $this->view($user, $ticket);
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        return $ticket->isClosed() && $this->view($user, $ticket);
    }

    public function viewInternal(User $user, Ticket $ticket): bool
    {
        return $this->isEditor($user);
    }

    private function isEditor(User $user): bool
    {
        return $user->can('edit tickets');
    }

    private function isOwner(User $user, Ticket $ticket): bool
    {
        return (int) $ticket->user_id === (int) $user->id;
    }
}
