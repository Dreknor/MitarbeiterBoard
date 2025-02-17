<?php

namespace App\Http\Controllers\Ticketsystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\createTicketCommentRequest;
use App\Mail\newTicketCommentMail;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketCommentController extends Controller
{




    /**
     * Store a newly created resource in storage.
     */
    public function store(createTicketCommentRequest $request,  $ticket)
    {

        try {
            $ticket = Ticket::findOrFail($ticket);
        } catch (\Exception $e) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found');
        }

        try {
            $comment = new TicketComment([
                'comment' => $request->comment,
                'ticket_id' => $ticket->id,
                'internal' => (auth()->user()->can('edit tickets') && $request->internal) ? true : false,
                'user_id' => auth()->id(),
            ]);
            $comment->save();


            if ($request->waiting_until) {
                $ticket->status = 'waiting';
                $ticket->waiting_until = $request->waiting_until;
                $ticket->save();

                $comment = new TicketComment([
                    'comment' => 'Ticket ist auf Warten bis ' . $ticket->waiting_until->format('d.m.Y H:i') . ' gesetzt',
                    'ticket_id' => $ticket->id,
                    'internal' => false,
                    'user_id' => auth()->id(),
                ]);

            } elseif ($ticket->status == 'waiting' and auth()->id() == $ticket->user_id) {
                $ticket->status = 'open';
                $ticket->waiting_until = null;
                $ticket->save();
                $comment = new TicketComment([
                    'comment' => 'Ticket ist wieder offen',
                    'ticket_id' => $ticket->id,
                    'internal' => false,
                    'user_id' => auth()->id(),
                ]);
            }


        } catch (\Exception $e) {
            Log::alert('Comment could not be saved: ' . $e->getMessage());
            return redirect()->route('tickets.show', $ticket->id)->with('error', 'Comment could not be saved');
        }


        try {
            // Notify the ticket creator if a public comment is added
            if ($comment->user_id !== $ticket->user_id) {
                Mail::to($ticket->user->email)->queue(mailable: new newTicketCommentMail($comment, $ticket));
            }

            // Notify the assigned user if the ticket creator responds
            if ($comment->user_id === $ticket->user_id && $ticket->assigned_to !== null) {
                $assignedUser = User::find($ticket->assigned_to);
                Mail::to($assignedUser->email)->queue(new newTicketCommentMail($comment, $ticket));
            }
        } catch (\Exception $e) {
            Log::alert('Comment-Mail could not be sent: ' . $e->getMessage());
        }

        return redirect()->route('tickets.show', $ticket->id);

    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
