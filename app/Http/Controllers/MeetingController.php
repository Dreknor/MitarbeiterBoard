<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\UpdateMeetingRequest;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\MeetingTask;
use App\Models\Room;
use App\Models\Theme;
use App\Services\Meetings\CreateMeetingWithRoomBookingAction;
use App\Services\Meetings\MeetingService;
use App\Services\Meetings\UpdateMeetingWithRoomBookingAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $meetings)
    {
    }

    /**
     * Prüft, ob ein Meeting gerade läuft (heute + innerhalb des Zeitfensters und nicht abgesagt).
     */
    private function isMeetingLive(Meeting $meeting): bool
    {
        if ($meeting->cancelled || ! $meeting->date->isSameDay(now())) {
            return false;
        }

        $start = \Carbon\Carbon::parse($meeting->date->format('Y-m-d') . ' ' . $meeting->start_time);
        $end   = \Carbon\Carbon::parse($meeting->date->format('Y-m-d') . ' ' . $meeting->end_time);

        return now()->between($start, $end);
    }

    /**
     * Stellt sicher, dass die Gruppe existiert und der angemeldete Nutzer Mitglied ist.
     * Gibt bei fehlendem Zugriff null + eine fertige Redirect-Response zurück.
     */
    private function resolveGroup(string $groupname, ?RedirectResponse &$denied): ?Group
    {
        $denied = null;
        $group  = Group::where('name', $groupname)->first();

        if (! $group || ! auth()->user()->groups()->contains($group)) {
            $denied = redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
            return null;
        }

        return $group;
    }

    /**
     * Display a listing of the resource.
     */
    public function index($groupname)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        $today         = now()->toDateString();
        $meetingsToday = Meeting::where('date', $today)
            ->where('group_id', $group->id)
            ->with(['themes', 'roomBooking.room', 'meetingTasks.user', 'invitationSender', 'participantUsers', 'participantGroups', 'participantRoles'])
            ->get();
        $otherMeetings = Meeting::query()
            ->where('group_id', $group->id)
            ->where('date', '>', $today)
            ->with(['themes', 'roomBooking.room', 'meetingTasks.user', 'invitationSender', 'participantUsers', 'participantGroups', 'participantRoles'])
            ->upcoming()
            ->get();

        // Offene Themen der Gruppe (für das "vorhandenes Thema zuweisen"-Dropdown)
        $openThemes = Theme::where('completed', false)
            ->where('group_id', $group->id)
            ->orderBy('date')
            ->get();

        return view('meetings.index', [
            'meetingsToday' => $meetingsToday,
            'otherMeetings' => $otherMeetings,
            'group'         => $group,
            'openThemes'    => $openThemes,
            'types'         => \App\Models\Type::all(),
            'bookableRooms' => Room::query()->where('bookable', true)->orderBy('room_number')->orderBy('name')->get(),
            'canBookRooms'  => auth()->user()->canAny(['create roomBooking', 'manage rooms']),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMeetingRequest $request, $groupname, CreateMeetingWithRoomBookingAction $action)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        try {
            $action->execute($group, $request->validated(), auth()->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting konnte nicht erstellt werden. ' . collect($e->errors())->flatten()->first(),
            ])->withErrors($e->errors());
        }

        return redirect()->route('meetings.index', ['group' => $groupname])->with([
            'type'    => 'success',
            'Meldung' => $request->boolean('book_room')
                ? 'Meeting erfolgreich erstellt und Raum gebucht'
                : 'Meeting erfolgreich erstellt',
        ]);
    }



    /**
     * Show the form for editing the specified resource.
     */
    public function edit($group, Meeting $meeting)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        if ((int) $meeting->group_id !== (int) $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting gehört nicht zur ausgewählten Gruppe',
            ]);
        }

        return view('meetings.edit', [
            'meeting' => $meeting->load('roomBooking.room'),
            'group'   => $group,
            'bookableRooms' => Room::query()->where('bookable', true)->orderBy('room_number')->orderBy('name')->get(),
            'canBookRooms'  => auth()->user()->canAny(['create roomBooking', 'manage rooms']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMeetingRequest $request, $group, Meeting $meeting, UpdateMeetingWithRoomBookingAction $action)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        if ((int) $meeting->group_id !== (int) $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting gehört nicht zur ausgewählten Gruppe',
            ]);
        }

        try {
            $action->execute($meeting, $request->validated(), auth()->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting konnte nicht bearbeitet werden. ' . collect($e->errors())->flatten()->first(),
            ])->withErrors($e->errors());
        }

        return redirect()->route('meetings.index', ['group' => $group->name])->with([
            'type'    => 'success',
            'Meldung' => $request->boolean('book_room')
                ? 'Meeting erfolgreich bearbeitet und Raumbuchung aktualisiert'
                : 'Meeting erfolgreich bearbeitet',
        ]);
    }

    /**
     * Remove the specified resource from storage (Soft-Delete).
     */
    public function destroy($group, Meeting $meeting): RedirectResponse
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        if ((int) $meeting->group_id !== (int) $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting gehört nicht zur ausgewählten Gruppe',
            ]);
        }

        $this->meetings->delete($meeting);

        return redirect()->route('meetings.index', ['group' => $group->name])->with([
            'type'    => 'success',
            'Meldung' => 'Meeting wurde gelöscht',
        ]);
    }

    /**
     * Speichert ein neues Thema oder weist ein bestehendes Thema zu.
     */
    public function storeTheme(Request $request, $group, $meetingId): RedirectResponse
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        $meeting = Meeting::where('group_id', $group->id)->findOrFail($meetingId);

        // Bestehendes Thema zuweisen
        if ($request->filled('existing_theme_id')) {
            $request->validate([
                'existing_theme_id' => [
                    'required',
                    Rule::exists('themes', 'id')->where('group_id', $group->id),
                ],
            ]);

            $theme = Theme::findOrFail((int) $request->input('existing_theme_id'));
            $this->meetings->attachTheme($meeting, $theme);
        }
        // Neues Thema anlegen
        else {
            $request->validate([
                'theme'    => 'required|string|max:255',
                'goal'     => 'required|string',
                'duration' => 'required|integer|min:5|max:240',
                'type'     => 'required|exists:types,id',
            ]);

            $theme = new Theme($request->only('theme', 'goal', 'duration'));
            $theme->group_id   = $group->id;
            $theme->type_id    = $request->input('type');
            $theme->creator_id = auth()->id();
            $theme->date       = $meeting->date;
            $theme->save();

            $meeting->themes()->attach($theme->id);
        }

        // Wenn das Meeting gerade läuft, direkt zum Thema springen
        if ($this->isMeetingLive($meeting)) {
            return redirect(url($group->name . '/themes/' . $theme->id))->with([
                'type'    => 'success',
                'Meldung' => 'Thema wurde dem Meeting zugewiesen.',
            ]);
        }

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Thema wurde dem Meeting zugewiesen.',
        ]);
    }

    public function cancelMeeting($groupname, Meeting $meeting)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        if ((int) $meeting->group_id !== (int) $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting gehört nicht zur ausgewählten Gruppe',
            ]);
        }

        $this->meetings->cancel($meeting, auth()->user());

        return redirect()->route('meetings.index', ['group' => $groupname])->with([
            'type'    => 'success',
            'Meldung' => 'Meeting erfolgreich abgesagt',
        ]);
    }

    /**
     * Hebt die Absage eines Meetings wieder auf.
     */
    public function reactivateMeeting($groupname, Meeting $meeting)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        if ((int) $meeting->group_id !== (int) $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting gehört nicht zur ausgewählten Gruppe',
            ]);
        }

        $this->meetings->reactivate($meeting);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Meeting wurde wieder aktiviert',
        ]);
    }

    /**
     * Entfernt ein Thema von einem Meeting.
     */
    public function removeTheme($group, Meeting $meeting, $themeId)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        if ((int) $meeting->group_id !== (int) $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting gehört nicht zur ausgewählten Gruppe',
            ]);
        }

        $meeting->themes()->detach($themeId);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Thema wurde vom Meeting entfernt',
        ]);
    }

    /**
     * Versendet Einladungen an alle Teilnehmenden (Gruppenmitglieder und zusätzlich Eingeladene).
     */
    public function sendInvitation(Request $request, $groupname, $meetingId)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        $meeting = Meeting::where('group_id', $group->id)->findOrFail($meetingId);
        $result  = $this->meetings->sendInvitations($meeting, $request->input('message'), auth()->user());

        return redirect()->back()->with($this->meetings->invitationFlash($result));
    }

    /**
     * Meetingsarchiv – Übersicht vergangener und abgesagter Meetings.
     */
    public function past($groupname)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        $pastMeetings = Meeting::where('group_id', $group->id)
            ->where(function ($query) {
                $query->where('date', '<', now()->toDateString())
                      ->orWhere('cancelled', true);
            })
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->with(['themes', 'meetingTasks.user', 'invitationSender'])
            ->get();

        return view('meetings.past', [
            'pastMeetings' => $pastMeetings,
            'group'        => $group,
        ]);
    }

    /**
     * Aufgaben für ein Meeting anzeigen und verwalten
     */
    public function tasks($group, Meeting $meeting)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        $users = $group->users;
        $tasks = $meeting->meetingTasks()->with('user')->get();

        return view('meetings.tasks', [
            'meeting' => $meeting,
            'group'   => $group,
            'users'   => $users,
            'tasks'   => $tasks,
        ]);
    }

    /**
     * Aufgabe zu einem Meeting hinzufügen
     */
    public function addTask(Request $request, $group, Meeting $meeting)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|string|max:255',
            'notes'   => 'nullable|string|max:255',
        ]);
        $meeting->meetingTasks()->create($validated);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Aufgabe hinzugefügt.',
        ]);
    }

    /**
     * Aufgabe bearbeiten
     */
    public function updateTask(Request $request, $group, Meeting $meeting, MeetingTask $task)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|string|max:255',
            'notes'   => 'nullable|string|max:255',
        ]);
        $task->update($validated);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Aufgabe aktualisiert.',
        ]);
    }

    /**
     * Aufgabe löschen
     */
    public function deleteTask($group, Meeting $meeting, MeetingTask $task)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        $task->delete();

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Aufgabe gelöscht.',
        ]);
    }

    /**
     * Weist alle offenen Themen des Tages dem Meeting zu.
     */
    public function assignAllThemesForDate($groupname, Meeting $meeting)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        // Alle offenen Themen der Gruppe mit gleichem Datum wie das Meeting
        $themes = Theme::where('completed', false)
            ->where('group_id', $group->id)
            ->whereDate('date', $meeting->date)
            ->get();

        $count = 0;
        foreach ($themes as $theme) {
            if (! $meeting->themes()->where('theme_id', $theme->id)->exists()) {
                $meeting->themes()->attach($theme->id);
                $count++;
            }
        }

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => $count . ' Themen wurden dem Meeting zugewiesen.',
        ]);
    }
}
