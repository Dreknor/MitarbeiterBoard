<?php

namespace App\Http\Controllers\Ticketsystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\createTicketRequest;
use App\Mail\TicketAssignmentMail;
use App\Mail\newTicketMail;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\Theme;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

class TicketController extends Controller
{


    public function createTicketsFromThemes($group)
    {
        // Fetch themes from the selected group
        $group = Group::where('name', $group)->first();

        $themes = $group->themes()->get();

        foreach ($themes as $theme) {
            try {
                // Determine priority based on theme priority
                $priority = 'low';
                if ($theme->priority > 75) {
                    $priority = 'high';
                } elseif ($theme->priority >= 40) {
                    $priority = 'medium';
                }

                // Create a new ticket
                $ticket = new Ticket([
                    'title' => $theme->theme,
                    'description' => $theme->information,
                    'priority' => $priority,
                    'user_id' => $theme->creator_id, // Set theme creator as ticket creator
                    'created_at' => $theme->created_at,
                    'updated_at' => $theme->updated_at,
                    'assigned_to' => $theme->assigned_to,
                    'status' => ($theme->completed) ? 'closed' : 'open',
                ]);
                $ticket->save();

                // Fetch protocols and create comments
                $protocols = $theme->protocols;
                foreach ($protocols as $protocol) {
                    $comment = new TicketComment([
                        'comment' => $protocol->protocol,
                        'ticket_id' => $ticket->id,
                        'user_id' => $protocol->creator_id, // Set protocol creator as comment creator
                        'created_at' => $protocol->created_at,
                        'updated_at' => $protocol->updated_at,
                    ]);
                    $comment->save();
                }
            } catch (\Exception $e) {
                Log::error('Ticketsystem: Ticket konnte nicht erstellt werden: ',
                    [
                        'group' => $group->name,
                        'theme' => $theme->theme,
                        'error' => $e->getMessage(),
                    ]
                );
            }




        }

        return redirect()->route('tickets.index');
    }

    public function __construct()
    {
        $this->middleware('can:view tickets');
    }

    //ToDo: Close Ticket when waiting_until is reached

    /**
     * Display a listing of the resource.
     */
    public function index($ticket = null)
    {
        if (auth()->user()->can('edit tickets')) {
            $tickets = Ticket::query()
                ->Open()
                ->with('user')
                ->with('category')
                ->with('assigned')
                ->with('comments')
                ->orderBy('created_at', 'desc')
                ->get();


        } else {
            $tickets = auth()->user()->tickets;
            $tickets = $tickets->filter(function ($ticket) {
                return $ticket->status != 'closed';
            })->load('category', 'assigned')->sortByDesc('comments.created_at');

        }

        $categories = Cache::remember('ticket_categories', 3600, function () {
            return TicketCategory::all();
        });

        $permission = Permission::where('name', 'edit tickets')->first();

        $users = User::whereHas('permissions', function ($query) use ($permission) {
            $query->where('id', $permission->id);
        })->orWhereHas('roles', function ($query) use ($permission) {
            $query->whereHas('permissions', function ($query) use ($permission) {
                $query->where('id', $permission->id);
            });
        })->get();

        return view('ticketsystem.index',
            [
                'tickets' => $tickets,
                'categories' => TicketCategory::all(),
                'show_ticket' => $ticket,
                'assignable' => $users
            ]
        );
    }

    /**
     * show ticket
     */

    public function show(Ticket $ticket)
    {
        return $this->index($ticket->load('comments'));
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(createTicketRequest $request)
    {
        $ticket = new Ticket($request->validated());
        $ticket->user_id = auth()->id();
        $ticket->save();


        try {
            $ticket->addAllMediaFromRequest()
                ->each(fn($fileAdder) => $fileAdder->toMediaCollection('ticket_files'));

        } catch (\Exception $e) {
            Log::error('Ticketsystem: Datei nicht hochgeladen: ',
                [
                    'ticket' => $ticket->id,
                    'user' => auth()->user()->id,
                    'email' => auth()->user()->email,
                    'error' => $e->getMessage(),
                    'files' => $request->file('ticket_files'),
                ]
            );
            return redirect()->back()->with('error', 'Datei konnte nicht hochgeladen werden');
        }

        try {
            $permission = Permission::where('name', 'edit tickets')->first();
            $users = User::whereHas('permissions', function ($query) use ($permission) {
                $query->where('id', $permission->id);
            })->orWhereHas('roles', function ($query) use ($permission) {
                $query->whereHas('permissions', function ($query) use ($permission) {
                    $query->where('id', $permission->id);
                });
            })->get();

            Log::debug('Ticketsystem: Ticket-Mail wird versendet: ',
                [
                    'ticket' => $ticket->title,
                    'user' => auth()->user()->id,
                    'email' => auth()->user()->email,
                ]

            );

            foreach ($users as $user) {
                $user->notify(new \App\Notifications\Push(
                    'Neues Ticket',
                    'Ein neues Ticket wurde erstellt: ' . $ticket->title
                ));
                Mail::to($user->email)->queue(new newTicketMail($ticket));
            }
        } catch (\Exception $e) {
           Log::error('Ticketsystem: Ticket-Mail konnte nicht versendet werden: ',
           [

                    'ticket' => $ticket->title,
                    'user' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]
            );
        }


        return redirect()->route('tickets.index');
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ticket $ticket)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ticket $ticket)
    {
        //
    }

    public function assign(Ticket $ticket, User $user)
    {
        $previousAssignedUser = $ticket->assigned;

        $ticket->assigned_to = $user->id;
        $ticket->save();

        // Create comment based on whether this is a reassignment or initial assignment
        if ($previousAssignedUser) {
            $ticket->comments()->create([
                'user_id' => auth()->id(),
                'comment' => 'Ticket von ' . $previousAssignedUser->name . ' an ' . $user->name . ' übertragen'
            ]);
        } else {
            $ticket->comments()->create([
                'user_id' => auth()->id(),
                'comment' => 'Ticket zugewiesen an ' . $user->name
            ]);
        }

        // Send email to new assignee if it's not the current user
        if (auth()->user()->id != $user->id) {
            try {
                Mail::to($user->email)->queue(new TicketAssignmentMail($ticket));
            } catch (\Exception $e) {
                Log::error(
                    'Ticketsystem: Ticket-Mail konnte nicht versendet werden: ',
                    [
                        'ticket' => $ticket->title,
                        'user' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        // Notify previous assignee about reassignment
        if ($previousAssignedUser && $previousAssignedUser->id != auth()->user()->id && $previousAssignedUser->id != $user->id) {
            try {
                $previousAssignedUser->notify(new \App\Notifications\Push(
                    'Ticket neu zugewiesen',
                    'Das Ticket "' . $ticket->title . '" wurde an ' . $user->name . ' übertragen'
                ));
            } catch (\Exception $e) {
                Log::error(
                    'Ticketsystem: Push-Benachrichtigung konnte nicht versendet werden: ',
                    [
                        'ticket' => $ticket->title,
                        'user' => $previousAssignedUser->id,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return redirect()->back();
    }

    public function close(Ticket $ticket)
    {
        $ticket->status = 'closed';
        $ticket->save();

        $ticket->comments()->create([
            'user_id' => auth()->id(),
            'comment' => 'Ticket closed'
        ]);

        return redirect()->route('tickets.index')->with('success', 'Ticket geschlossen');
    }

    /*
     * pin and unpin ticket for user
     *
     *
     */

    public function pin(Ticket $ticket)
    {
        auth()->user()->pinned_tickets()->attach($ticket->id);

        return redirect()->back();
    }

    /**
     * list archived tickets
     */
    public function archived($ticket = null)
    {
        if (auth()->user()->can('edit tickets')) {
            $tickets = Ticket::query()
                ->where('status', 'closed')
                ->with('user')
                ->with('category')
                ->with('assigned')
                ->with('comments')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $tickets = auth()->user()->tickets->where('status', 'closed')->load('category', 'assigned')->sortByDesc('comments.created_at');
        }

        $categories = Cache::remember('ticket_categories', 3600, function () {
            return TicketCategory::all();
        });

        return view('ticketsystem.archiv',
            [
                'tickets' => $tickets,
                'categories' => TicketCategory::all(),
                'show_ticket' => $ticket
            ]
        );
    }

    public function showClosedTicket(Ticket $ticket)
    {
        return $this->archived($ticket->load('comments'));
    }

    public function closeTicketAfterTime()
    {

        if (settings('ticket_closed_automatic')) {

            $days = settings('ticket_closed_automatic_days') ?? 7;

            $tickets = Ticket::query()
                ->where('status', 'waiting')
                ->where('waiting_until', '<', now()->subDays($days))
                ->get();

            foreach ($tickets as $ticket) {
                $ticket->status = 'closed';
                $ticket->save();

                Log::info('Ticketsystem: Ticket wurde automatisch geschlossen: ',
                    [
                        'ticket' => $ticket->title,
                        'user' => auth()->user()->id,
                        'email' => auth()->user()->email,
                    ]
                );

                $comment = new TicketComment([
                    'comment' => 'Das Ticket wurde automatisch geschlossen, da keine Rückmeldung erfolgte',
                    'ticket_id' => $ticket->id,
                    'user_id' => null,
                    'internal' => false
                ]);
                $comment->save();
            }


        }





    }


}
