<?php

namespace App\Http\Controllers\Ticketsystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\createTicketRequest;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class TicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view tickets');
    }


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
            $tickets = auth()->user()->tickets->load('category', 'assigned')->sortByDesc('comments.created_at');

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
        $ticket->assigned_to = $user->id;
        $ticket->save();

        $ticket->comments()->create([
            'user_id' => auth()->id(),
            'comment' => 'Ticket zugewiesen an ' . $user->name
        ]);

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




}
