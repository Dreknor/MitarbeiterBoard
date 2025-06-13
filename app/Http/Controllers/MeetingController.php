<?php

namespace App\Http\Controllers;

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
        return view('meetings.index', [
            'meetingsToday' => $meetingsToday,
            'otherMeetings' => $otherMeetings,
            'group'         => $group,
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
}
