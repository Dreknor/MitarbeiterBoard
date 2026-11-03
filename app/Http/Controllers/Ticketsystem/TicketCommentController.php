<?php

namespace App\Http\Controllers\Ticketsystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\createTicketCommentRequest;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Support\Carbon;

class TicketCommentController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
        $this->middleware('permission:view tickets');
    }

    /**
     * Kommentar speichern; Bearbeiter können ihn intern markieren und das
     * Ticket auf "wartend" setzen. Antwortet der Ersteller auf ein wartendes
     * Ticket, wird es automatisch wieder geöffnet.
     */
    public function store(createTicketCommentRequest $request, Ticket $ticket)
    {
        $this->tickets->addComment(
            $ticket,
            $request->user(),
            $request->validated('comment'),
            $request->boolean('internal'),
            $request->filled('waiting_until') ? Carbon::parse($request->validated('waiting_until')) : null,
            $request->file('files', []),
        );

        return redirect()->route('tickets.show', $ticket)->with([
            'type' => 'success',
            'Meldung' => 'Kommentar gespeichert.',
        ]);
    }
}
