<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMeetingRequest;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Room;
use App\Services\Meetings\CreateMeetingWithRoomBookingAction;
use App\Services\Meetings\MeetingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gruppenübergreifende Meeting-Übersicht: alle Meetings, an denen der Nutzer
 * teilnimmt (Gruppen-Meetings und freie Besprechungen), Archiv und Anlegen.
 */
class MeetingOverviewController extends Controller
{
    public function __construct(private readonly MeetingService $meetings)
    {
    }

    public function index(Request $request)
    {
        $user   = auth()->user();
        $filter = (string) $request->query('filter', 'all');
        $today  = now()->toDateString();

        $meetings = $this->filtered(Meeting::query()->visibleTo($user), $filter)
            ->where('date', '>=', $today)
            ->with(['group', 'creator', 'roomBooking.room', 'participantUsers', 'participantGroups', 'participantRoles'])
            ->withCount('themes')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $weekEnd  = now()->endOfWeek()->toDateString();
        $sections = [
            'Heute'       => $meetings->filter(fn (Meeting $m) => $m->date->toDateString() === $today),
            'Diese Woche' => $meetings->filter(fn (Meeting $m) => $m->date->toDateString() > $today && $m->date->toDateString() <= $weekEnd),
            'Später'      => $meetings->filter(fn (Meeting $m) => $m->date->toDateString() > $weekEnd),
        ];

        $meetingGroups = $user->groups_rel()->where('use_meetings', true)->orderBy('name')->get();

        return view('meetings.overview', [
            'sections'       => array_filter($sections, fn ($items) => $items->isNotEmpty()),
            'total'          => $meetings->count(),
            'stats'          => [
                'heute'       => $sections['Heute']->where('cancelled', false)->count(),
                'woche'       => $meetings->filter(fn (Meeting $m) => $m->date->toDateString() <= $weekEnd && ! $m->cancelled)->count(),
                'frei'        => $meetings->whereNull('group_id')->count(),
                'organisiert' => $meetings->filter(fn (Meeting $m) => (int) $m->creator_id === (int) $user->id)->count(),
            ],
            'filter'         => $filter,
            'filterGroups'   => $this->filterGroups($user),
            'meetingGroups'  => $meetingGroups,
            'canCreateFree'  => $user->can('create free meetings'),
            'canCreate'      => $user->can('create', Meeting::class),
            'options'        => $this->meetings->participantOptions(),
            'bookableRooms'  => Room::query()->where('bookable', true)->orderBy('room_number')->orderBy('name')->get(),
            'canBookRooms'   => $user->canAny(['create roomBooking', 'manage rooms']),
        ]);
    }

    public function archive(Request $request)
    {
        $user   = auth()->user();
        $filter = (string) $request->query('filter', 'all');
        $search = trim((string) $request->query('q', ''));

        $meetings = $this->filtered(Meeting::query()->visibleTo($user), $filter)
            ->where(function (Builder $q) {
                $q->where('date', '<', now()->toDateString())
                    ->orWhere('cancelled', true);
            })
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%' . $search . '%'))
            ->with(['group', 'creator'])
            ->withCount('themes')
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->paginate(25)
            ->withQueryString();

        return view('meetings.archive', [
            'meetings'     => $meetings,
            'filter'       => $filter,
            'search'       => $search,
            'filterGroups' => $this->filterGroups($user),
        ]);
    }

    public function store(SaveMeetingRequest $request, CreateMeetingWithRoomBookingAction $action): RedirectResponse
    {
        $user  = auth()->user();
        $group = null;

        if ($request->filled('group_id')) {
            $group = $user->groups_rel()->where('groups.id', $request->integer('group_id'))->first();
            if (! $group) {
                return redirect()->back()->withInput()->with([
                    'type'    => 'warning',
                    'Meldung' => 'Du bist kein Mitglied dieser Gruppe.',
                ]);
            }
        } elseif (! $user->can('create free meetings')) {
            return redirect()->back()->withInput()->with([
                'type'    => 'warning',
                'Meldung' => 'Keine Berechtigung, Meetings ohne Gruppe anzulegen.',
            ]);
        }

        try {
            $meeting = DB::transaction(function () use ($action, $group, $request, $user) {
                $meeting = $action->execute($group, $request->validated(), $user);
                $this->meetings->syncParticipants($meeting, $request->participantData());

                return $meeting;
            });
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting konnte nicht erstellt werden. ' . collect($e->errors())->flatten()->first(),
            ])->withErrors($e->errors());
        }

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Meeting wurde erstellt. Jetzt Themen hinzufügen und Teilnehmende einladen.',
        ]);
    }

    /**
     * Filter: all | free | mine | group-{id}
     */
    private function filtered(Builder $query, string $filter): Builder
    {
        return match (true) {
            $filter === 'free'                 => $query->whereNull('group_id'),
            $filter === 'mine'                 => $query->where('creator_id', auth()->id()),
            str_starts_with($filter, 'group-') => $query->where('group_id', (int) substr($filter, 6)),
            default                            => $query,
        };
    }

    /**
     * Gruppen, zu denen der Nutzer Meetings sehen kann (für die Filterleiste).
     */
    private function filterGroups($user)
    {
        $groupIds = Meeting::query()->visibleTo($user)->whereNotNull('group_id')->distinct()->pluck('group_id');

        return Group::query()->whereIn('id', $groupIds)->orderBy('name')->get(['id', 'name']);
    }
}
