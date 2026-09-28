<?php

namespace App\Http\Controllers\Ticketsystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\createTicketRequest;
use App\Http\Requests\updateTicketRequest;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
        $this->middleware('permission:view tickets');
    }

    /**
     * Offene Tickets (Bearbeiter: alle, sonst nur eigene) inkl. Filter.
     * Rechts wird entweder das Formular für ein neues Ticket oder $showTicket angezeigt.
     */
    public function index(Request $request, ?Ticket $showTicket = null)
    {
        $user = $request->user();
        $isEditor = $user->can('edit tickets');

        $filters = [
            'scope' => $request->query('scope', 'all'),
            'category' => $request->query('category'),
            'priority' => $request->query('priority'),
            'status' => $request->query('status'),
            'q' => trim((string) $request->query('q', '')),
            'sort' => $request->query('sort', 'activity'),
        ];

        $query = Ticket::query()
            ->visibleTo($user)
            ->open()
            ->with(['user', 'category', 'assigned'])
            ->withMax('comments', 'created_at')
            // Nur echte Antworten zählen; interne sieht nur, wer sie lesen darf
            ->withCount(['comments' => function ($q) use ($isEditor) {
                $q->where('system', false);
                if (!$isEditor) {
                    $q->where('internal', false);
                }
            }]);

        if ($isEditor) {
            match ($filters['scope']) {
                'mine' => $query->where('assigned_to', $user->id),
                'unassigned' => $query->whereNull('assigned_to'),
                'created' => $query->where('user_id', $user->id),
                default => null,
            };
        }

        if (filled($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (in_array($filters['priority'], array_keys(Ticket::PRIORITY_LABELS), true)) {
            $query->where('priority', $filters['priority']);
        }

        if (in_array($filters['status'], [Ticket::STATUS_OPEN, Ticket::STATUS_WAITING], true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $this->applySearch($query, $filters['q']);
        }

        $tickets = $query->get();

        $priorityRank = ['high' => 0, 'medium' => 1, 'low' => 2];
        $tickets = match ($filters['sort']) {
            'priority' => $tickets->sortBy([
                fn ($a, $b) => ($priorityRank[$a->priority] ?? 3) <=> ($priorityRank[$b->priority] ?? 3),
                fn ($a, $b) => $b->last_activity <=> $a->last_activity,
            ]),
            'created' => $tickets->sortByDesc('created_at'),
            default => $tickets->sortByDesc(fn ($t) => $t->last_activity?->timestamp ?? 0),
        };

        $stats = null;
        if ($isEditor) {
            $stats = [
                'open' => Ticket::open()->count(),
                'unassigned' => Ticket::open()->whereNull('assigned_to')->count(),
                'mine' => Ticket::open()->where('assigned_to', $user->id)->count(),
                'waiting' => Ticket::where('status', Ticket::STATUS_WAITING)->count(),
                'overdue' => Ticket::where('status', Ticket::STATUS_WAITING)->where('waiting_until', '<', now())->count(),
            ];
        }

        return view('ticketsystem.index', [
            'tickets' => $tickets->values(),
            'categories' => $this->tickets->categories(),
            'show_ticket' => $showTicket ? $this->prepareForDisplay($showTicket, $user) : null,
            'assignable' => $isEditor ? $this->tickets->editors() : collect(),
            'pinned' => $user->pinned_tickets()->visibleTo($user)->with('category')->get(),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        return $this->index($request, $ticket);
    }

    public function store(createTicketRequest $request)
    {
        $ticket = $this->tickets->create(
            $request->user(),
            $request->validated(),
            $request->file('files', []),
        );

        return redirect()->route('tickets.show', $ticket)->with([
            'type' => 'success',
            'Meldung' => 'Ticket wurde erstellt.',
        ]);
    }

    /**
     * Titel, Kategorie oder Priorität ändern (nur Bearbeiter).
     */
    public function update(updateTicketRequest $request, Ticket $ticket)
    {
        $this->tickets->updateDetails($ticket, $request->validated(), $request->user());

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Ticket aktualisiert.',
        ]);
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $this->authorize('manage', $ticket);

        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $assignee = null;
        if (!empty($data['user_id'])) {
            $assignee = User::findOrFail($data['user_id']);

            if (!$assignee->can('edit tickets')) {
                return redirect()->back()->with([
                    'type' => 'danger',
                    'Meldung' => $assignee->name.' darf keine Tickets bearbeiten.',
                ]);
            }
        }

        $this->tickets->assign($ticket, $assignee, $request->user());

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => $assignee ? 'Ticket an '.$assignee->name.' zugewiesen.' : 'Zuweisung aufgehoben.',
        ]);
    }

    public function close(Request $request, Ticket $ticket)
    {
        $this->authorize('close', $ticket);

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $reason = filled($data['reason'] ?? null) ? 'Ticket geschlossen: '.$data['reason'] : null;
        $this->tickets->close($ticket, $request->user(), $reason);

        return redirect()->route('tickets.index')->with([
            'type' => 'success',
            'Meldung' => 'Ticket geschlossen.',
        ]);
    }

    public function reopen(Request $request, Ticket $ticket)
    {
        $this->authorize('reopen', $ticket);

        $this->tickets->reopen($ticket, $request->user());

        return redirect()->route('tickets.show', $ticket)->with([
            'type' => 'success',
            'Meldung' => 'Ticket wurde wieder geöffnet.',
        ]);
    }

    /**
     * Ticket für den aktuellen Nutzer anpinnen bzw. lösen.
     */
    public function pin(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $pinned = $this->tickets->togglePin($ticket, $request->user());

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => $pinned ? 'Ticket angepinnt.' : 'Ticket gelöst.',
        ]);
    }

    /**
     * Anhang eines Tickets oder Kommentars ausliefern – nur für Personen, die das
     * Ticket (bzw. den internen Kommentar) sehen dürfen.
     */
    public function file(Ticket $ticket, Media $media)
    {
        $this->authorize('view', $ticket);

        $belongsToTicket = match ($media->model_type) {
            Ticket::class => (int) $media->model_id === (int) $ticket->id,
            TicketComment::class => ($comment = TicketComment::find($media->model_id)) !== null
                && (int) $comment->ticket_id === (int) $ticket->id
                && (!$comment->internal || auth()->user()->can('viewInternal', $ticket)),
            default => false,
        };

        abort_unless($belongsToTicket, 404);

        $path = $media->getPath();
        abort_unless(is_file($path), 404, 'Datei nicht gefunden');

        $response = response()->file($path, ['Content-Type' => $media->mime_type]);
        $response->setContentDisposition('inline', $media->file_name, \Illuminate\Support\Str::ascii($media->file_name));

        return $response;
    }

    /**
     * Geschlossene Tickets (Bearbeiter: alle, sonst nur eigene).
     */
    public function archived(Request $request, ?Ticket $showTicket = null)
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        $query = Ticket::query()
            ->visibleTo($user)
            ->closed()
            ->with(['category', 'user'])
            ->orderByDesc('closed_at')
            ->orderByDesc('updated_at');

        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        return view('ticketsystem.archiv', [
            'tickets' => $query->paginate(50)->withQueryString(),
            'categories' => $this->tickets->categories(),
            'show_ticket' => $showTicket ? $this->prepareForDisplay($showTicket, $user) : null,
            'assignable' => collect(),
            'search' => $search,
        ]);
    }

    public function showClosedTicket(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        if (!$ticket->isClosed()) {
            return redirect()->route('tickets.show', $ticket);
        }

        return $this->archived($request, $ticket);
    }

    /**
     * Einmaliger Import der Themen einer Gruppe als Tickets.
     */
    public function createTicketsFromThemes($group)
    {
        abort_unless(auth()->user()->can('edit tickets'), 403);

        $group = Group::where('name', $group)->firstOrFail();

        $imported = 0;
        foreach ($group->themes()->get() as $theme) {
            try {
                $priority = 'low';
                if ($theme->priority > 75) {
                    $priority = 'high';
                } elseif ($theme->priority >= 40) {
                    $priority = 'medium';
                }

                $ticket = new Ticket([
                    'title' => $theme->theme,
                    'description' => $theme->information ?? '',
                    'priority' => $priority,
                    'user_id' => $theme->creator_id,
                    'assigned_to' => $theme->assigned_to,
                    'status' => $theme->completed ? Ticket::STATUS_CLOSED : Ticket::STATUS_OPEN,
                    'closed_at' => $theme->completed ? $theme->updated_at : null,
                ]);
                $ticket->created_at = $theme->created_at;
                $ticket->updated_at = $theme->updated_at;
                $ticket->save();

                foreach ($theme->protocols as $protocol) {
                    $comment = new TicketComment([
                        'comment' => $protocol->protocol,
                        'ticket_id' => $ticket->id,
                        'user_id' => $protocol->creator_id,
                    ]);
                    $comment->created_at = $protocol->created_at;
                    $comment->updated_at = $protocol->updated_at;
                    $comment->save();
                }

                $imported++;
            } catch (\Exception $e) {
                Log::error('Ticketsystem: Ticket konnte nicht erstellt werden: ', [
                    'group' => $group->name,
                    'theme' => $theme->theme,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('tickets.index')->with([
            'type' => 'success',
            'Meldung' => $imported.' Themen als Tickets importiert.',
        ]);
    }

    /**
     * Scheduler: wartende Tickets nach Ablauf der Frist automatisch schließen.
     */
    public function closeTicketAfterTime()
    {
        $this->tickets->closeExpiredWaiting();
    }

    private function applySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like);

            if (ctype_digit(ltrim($search, '#'))) {
                $q->orWhere('id', (int) ltrim($search, '#'));
            }
        });
    }

    /**
     * Lädt Kommentare (ohne interne für Nicht-Bearbeiter), Dateien und Relationen.
     */
    private function prepareForDisplay(Ticket $ticket, User $user): Ticket
    {
        $showInternal = $user->can('viewInternal', $ticket);

        return $ticket->load([
            'user',
            'assigned',
            'category',
            'closedBy',
            'media',
            'comments' => function ($q) use ($showInternal) {
                $q->with(['user', 'media'])->oldest()->oldest('id');
                if (!$showInternal) {
                    $q->where('internal', false);
                }
            },
        ]);
    }
}
