<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Http\Requests\MeetingRequest;
use App\Models\Group;
use App\Models\Meeting;
use Illuminate\Http\Request;

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
        $meetingsToday = Meeting::where('date', $today)->with('themes')->get();
        $otherMeetings = Meeting::query()->upcoming()->get();
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
    public function edit(Meeting $meeting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Meeting $meeting)
    {
        //
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
            $theme->save();

            $meeting->themes()->attach($theme->id);

        }
        // Bestehendes Thema zuweisen
        elseif ($request->filled('existing_theme_id')) {
            $themeId = $request->input('existing_theme_id');
            if (!$meeting->themes()->where('theme_id', $themeId)->exists()) {
                $meeting->themes()->attach($themeId);
            }
        }
        return redirect()->back()->with('success', 'Thema wurde dem Meeting zugewiesen.');
    }
}
