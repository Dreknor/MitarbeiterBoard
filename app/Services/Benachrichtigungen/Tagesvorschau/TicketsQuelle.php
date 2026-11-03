<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\TagesvorschauEinstellung;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Offene Tickets, die der Person zugewiesen sind (Bearbeitende).
 */
class TicketsQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'tickets';
    }

    public function label(): string
    {
        return 'Meine Tickets';
    }

    public function icon(): string
    {
        return 'fa-ticket-alt';
    }

    public function sichtbarFuer(User $user): bool
    {
        return $user->can('edit tickets');
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        return Ticket::query()
            ->open()
            ->where('assigned_to', $user->id)
            ->orderBy('created_at')
            ->limit(15)
            ->get()
            ->map(fn (Ticket $ticket) => new TagesvorschauEintrag(
                titel: (string) $ticket->title,
                details: 'seit '.$ticket->created_at->format('d.m.Y'),
                url: route('tickets.show', $ticket),
            ));
    }
}
