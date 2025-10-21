<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Http\Requests\MeetingRequest;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\MeetingTask;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MeetingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($groupname)
    {

        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $today = now()->toDateString();
        $meetingsToday = Meeting::where('date', $today)->where('group_id', $group->id)->with('themes')->get();
        $otherMeetings = Meeting::query()->where('group_id', $group->id)->upcoming()->get();
        // Offene Themen aus älteren Meetings, die noch nicht abgeschlossen sind und nicht im aktuellen Meeting sind
        $openThemes = \App\Models\Theme::where('completed', false)
            ->where('group_id', $group->id)
            ->get();

        return view('meetings.index', [
            'meetingsToday' => $meetingsToday,
            'otherMeetings' => $otherMeetings,
            'group'         => $group,
            'openThemes'    => $openThemes,
            'types'          => \App\Models\Type::all(),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(MeetingRequest $request, $groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
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
        $group = Group::where('name', $group)->first();
        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        return view('meetings.edit', [
            'meeting' => $meeting,
            'group' => $group,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $group, Meeting $meeting)
    {
        $group = Group::where('name', $group)->first();
        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);
        $meeting->update($validated);
        return redirect()->route('meetings.index', ['group' => $group->name])->with([
            'type'    => 'success',
            'Meldung' => 'Meeting erfolgreich bearbeitet',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Meeting $meeting)
    {
        //
    }

    /**
     * Speichert ein neues Thema oder weist ein bestehendes Thema zu.
     */
    public function storeTheme(Request $request, $group, $meetingId): \Illuminate\Http\RedirectResponse
    {
        $group = Group::where('name', $group)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        // Validierung der Anfrage
            if ($request->filled('existing_theme_id')) {
                $request->validate([
                    'existing_theme_id' => 'required|exists:themes,id',
                ]);
            } else  {
                $request->validate([
                    'theme' => 'required|string|max:255',
                    'goal' => 'required|string',
                    'duration' => 'required|integer|min:5|max:240',
                    'type'     => 'required|exists:types,id',
                ]);
            }


        $meeting = \App\Models\Meeting::findOrFail($meetingId);
        // Neues Thema anlegen
        if ($request->filled('theme') && !$request->filled('existing_theme_id')) {
            $theme = new \App\Models\Theme(
                $request->only('theme', 'goal', 'duration')
            );
            $theme->group_id = $group->id;
            $theme->type_id = $request->input('type');
            $theme->creator_id = auth()->id();
            $theme->date = $meeting->date;
            $theme->save();

            $meeting->themes()->attach($theme->id);

        }
        // Bestehendes Thema zuweisen
        elseif ($request->filled('existing_theme_id')) {
            $themeId = $request->input('existing_theme_id');
            if (!$meeting->themes()->where('theme_id', $themeId)->exists()) {
                $meeting->themes()->attach($themeId);
                Theme::query()->where('id', $themeId)->update(['date' => $meeting->date]);
            }
        }

        if ($meeting->date == today()->toDateString() and $meeting->start_time <= now()->format('H:i') and $meeting->end_time >= now()){

            return redirect(url($group->name.'/themes/'.$theme->id))->with('success', 'Thema wurde dem Meeting zugewiesen.');

        }
        return redirect()->back()->with('success', 'Thema wurde dem Meeting zugewiesen.');
    }

    public function cancelMeeting($groupname, Meeting $meeting)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $meeting->update([
            'cancelled' => true,
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);

        return redirect()->route('meetings.index', ['group' => $groupname])->with([
            'type'    => 'success',
            'Meldung' => 'Meeting erfolgreich abgesagt',
        ]);

    }

    /**
     * Entfernt ein Thema von einem Meeting.
     */
    public function removeTheme($group, Meeting $meeting, $themeId)
    {
        $group = Group::where('name', $group)->first();
        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
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
        $group = \App\Models\Group::where('name', $groupname)->firstOrFail();
        $meeting = \App\Models\Meeting::with('themes')->findOrFail($meetingId);
        $message = $request->input('message');
        $users = $group->users;

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new \App\Mail\MeetingInvitationMail($meeting, $group, $user, $message, auth()->user()->name));
        }

        // Historie speichern
        $meeting->update([
            'invitation_sent_at' => now(),
            'invitation_sent_by' => auth()->id(),
        ]);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Einladungen wurden an alle Gruppenmitglieder versendet.'
        ]);
    }

    /**
     * Übersicht vergangener Meetings
     */
    public function past($groupname)
    {
        $group = Group::where('name', $groupname)->first();
        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        $pastMeetings = Meeting::where('group_id', $group->id)
            ->where('date', '<', now()->toDateString())
            ->orderBy('date', 'desc')
            ->with('themes')
            ->get();
        return view('meetings.past', [
            'pastMeetings' => $pastMeetings,
            'group' => $group,
        ]);
    }

    /**
     * Aufgaben für ein Meeting anzeigen und verwalten
     */
    public function tasks($group, Meeting $meeting)
    {
        $group = Group::where('name', $group)->first();
        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        $users = $group->users;
        $tasks = $meeting->meetingTasks()->with('user')->get();
        return view('meetings.tasks', [
            'meeting' => $meeting,
            'group' => $group,
            'users' => $users,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Aufgabe zu einem Meeting hinzufügen
     */
    public function addTask(Request $request, $group, Meeting $meeting)
    {
        $group = Group::where('name', $group)->first();
        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);
        $meeting->meetingTasks()->create($validated);
        return redirect()->back()->with('success', 'Aufgabe hinzugefügt.');
    }

    /**
     * Aufgabe bearbeiten
     */
    public function updateTask(Request $request, $group, Meeting $meeting, MeetingTask $task)
    {
        $group = Group::where('name', $group)->first();
        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);
        $task->update($validated);
        return redirect()->back()->with('success', 'Aufgabe aktualisiert.');
    }

    /**
     * Aufgabe löschen
     */
    public function deleteTask($group, Meeting $meeting, MeetingTask $task)
    {
        $group = Group::where('name', $group)->first();
        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        $task->delete();
        return redirect()->back()->with('success', 'Aufgabe gelöscht.');
    }

    /**
     * Weist alle offenen Themen des Tages dem Meeting zu.
     */
    public function assignAllThemesForDate($groupname, Meeting $meeting)
    {
        $group = Group::where('name', $groupname)->first();
        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }
        // Alle offenen Themen der Gruppe mit gleichem Datum wie das Meeting
        $themes = \App\Models\Theme::where('completed', false)
            ->where('group_id', $group->id)
            ->whereDate('date', $meeting->date)
            ->get();
        $count = 0;
        foreach ($themes as $theme) {
            if (!$meeting->themes()->where('theme_id', $theme->id)->exists()) {
                $meeting->themes()->attach($theme->id);
                $count++;
            }
        }
        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => $count . ' Themen wurden dem Meeting zugewiesen.'
        ]);
    }
}
