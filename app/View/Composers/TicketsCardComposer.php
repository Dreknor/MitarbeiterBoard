<?php

namespace App\View\Composers;

use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TicketsCardComposer
{
    public function __construct()
    {
    }

    public function compose(View $view): void
    {
        $user = Auth::user();

        if (!$user) {
            $view->with('ticketsCardTickets', collect());
            return;
        }

        // Basis-Query: offene oder wartende Tickets sauber geklammert
        $query = Ticket::query()
            ->where(function($q){
                $q->where('status', 'open')->orWhere('status', 'waiting');
            })
            ->with(['comments' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->with('assigned')
            ->with('user');

        if ($user->can('edit tickets')) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('assigned_to')
                  ->orWhere('assigned_to', $user->id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        $tickets = $query->get()->map(function ($ticket) {
            $lastComment = $ticket->comments->first();
            $ticket->last_activity = ($lastComment && $lastComment->created_at && $lastComment->created_at->greaterThan($ticket->updated_at))
                ? $lastComment->created_at
                : $ticket->updated_at;
            return $ticket;
        })->sortByDesc('last_activity')->take(6);

        $view->with('ticketsCardTickets', $tickets);
    }
}
