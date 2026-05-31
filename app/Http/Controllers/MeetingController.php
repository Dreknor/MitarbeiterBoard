<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Http\Requests\MeetingRequest;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\MeetingTask;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
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
        $meetingsToday = Meeting::where('date', $today)->where('group_id', $group->id)->with('themes')->get();
        $otherMeetings = Meeting::query()->where('group_id', $group->id)->upcoming()->get();

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
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(MeetingRequest $request, $groupname)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        $meeting = new Meeting($request->validated());
        $meeting->group_id = $group->id;
        $meeting->save();

        return redirect()->route('meetings.index', ['group' => $groupname])->with([
            'type'    => 'success',
            'Meldung' => 'Meeting erfolgreich erstellt',
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

        return view('meetings.edit', [
            'meeting' => $meeting,
            'group'   => $group,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $group, Meeting $meeting)
    {
        $group = $this->resolveGroup($group, $denied);
        if (! $group) {
            return $denied;
        }

        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'date'       => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
        ]);
        $meeting->update($validated);

        return redirect()->route('meetings.index', ['group' => $group->name])->with([
            'type'    => 'success',
            'Meldung' => 'Meeting erfolgreich bearbeitet',
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

        // Verknüpfung zu Themen lösen (die Themen selbst bleiben erhalten)
        $meeting->themes()->detach();
        $meeting->delete();

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

            if (! $meeting->themes()->where('theme_id', $theme->id)->exists()) {
                $meeting->themes()->attach($theme->id);
                $theme->update(['date' => $meeting->date]);
            }
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

        $meeting->update([
            'cancelled'    => true,
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);

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

        $meeting->update([
            'cancelled'    => false,
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);

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

        $meeting->themes()->detach($themeId);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Thema wurde vom Meeting entfernt',
        ]);
    }

    /**
     * Versendet Einladungen an alle User der Gruppe für ein Meeting
     */
    public function sendInvitation(Request $request, $groupname, $meetingId)
    {
        $group = $this->resolveGroup($groupname, $denied);
        if (! $group) {
            return $denied;
        }

        $meeting = Meeting::with('themes')->where('group_id', $group->id)->findOrFail($meetingId);
        $message = $request->input('message');
        $users   = $group->users;

        $gesendet   = 0;
        $fehlerhaft = [];

        foreach ($users as $user) {
            if (empty($user->email)) {
                Log::warning('Meeting-Einladung: Kein E-Mail-Adresse für Benutzer', [
                    'user_id'    => $user->id,
                    'user_name'  => $user->name,
                    'meeting_id' => $meeting->id,
                ]);
                $fehlerhaft[] = $user->name . ' (keine E-Mail-Adresse)';
                continue;
            }

            try {
                Mail::to($user->email)->queue(
                    new \App\Mail\MeetingInvitationMail($meeting, $group, $user, $message, auth()->user()->name, auth()->user()->email)
                );
                $gesendet++;
            } catch (\Throwable $e) {
                Log::error('Meeting-Einladung: Fehler beim Einreihen der Mail', [
                    'user_id'    => $user->id,
                    'user_email' => $user->email,
                    'meeting_id' => $meeting->id,
                    'error'      => $e->getMessage(),
                ]);
                $fehlerhaft[] = $user->name . ' (' . $user->email . ')';
            }
        }

        // Historie nur speichern, wenn mindestens eine Mail eingereiht wurde
        if ($gesendet > 0) {
            $meeting->update([
                'invitation_sent_at' => now(),
                'invitation_sent_by' => auth()->id(),
            ]);
            Log::info('Meeting-Einladungen eingereiht', [
                'meeting_id'   => $meeting->id,
                'gesendet'     => $gesendet,
                'fehlerhaft'   => count($fehlerhaft),
                'versender_id' => auth()->id(),
            ]);
        }

        if (! empty($fehlerhaft)) {
            $meldung = "Einladungen wurden an {$gesendet} Mitglieder eingereiht. "
                . 'Folgende Empfänger konnten nicht berücksichtigt werden: '
                . implode(', ', $fehlerhaft);
            $typ = $gesendet > 0 ? 'warning' : 'danger';
        } else {
            $meldung = "Einladungen wurden an {$gesendet} Gruppenmitglieder eingereiht.";
            $typ     = 'success';
        }

        return redirect()->back()->with([
            'type'    => $typ,
            'Meldung' => $meldung,
        ]);
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
