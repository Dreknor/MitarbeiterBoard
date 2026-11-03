<?php

namespace App\View\Composers;

use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TicketsCardComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();

        if (!$user || !$user->can('view tickets')) {
            $view->with('ticketsCardTickets', collect());
            return;
        }

        $query = Ticket::query()
            ->open()
            ->withMax('comments', 'created_at')
            ->with(['assigned', 'user']);

        if ($user->can('edit tickets')) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('assigned_to')
                  ->orWhere('assigned_to', $user->id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        // last_activity berücksichtigt den neuesten Kommentar (Accessor am Model)
        $tickets = $query->get()
            ->sortByDesc(fn (Ticket $ticket) => $ticket->last_activity?->timestamp ?? 0)
            ->take(6);

        $view->with('ticketsCardTickets', $tickets);
    }
}
