<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($groupname, $date = null)
    {
        $group = Group::where('name', $groupname)->first();
        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }


       if ($date == null) {
            $date = now();
        } else {
            $date = \Carbon\Carbon::createFromFormat('Ymd', $date);
        }

        $presences = Presence::where('group_id', $group->id)
            ->where('date', $date->format('Y-m-d'))
            ->with('user')
            ->get();

        return view('themes.presence', [
            'group'     => $group,
            'date'      => $date,
            'presences' => $presences,
            'users'     => $group->users->sortBy('name'),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $groupname)
    {
        $group = Group::where('name', $groupname)->first();
        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $users = $group->users;

        $date = Carbon::now();

        foreach ($users as $user) {
            if ($request->get('presence_'.$user->id) != null){
                $presence = Presence::firstOrNew([
                    'group_id' => $group->id,
                    'date'     => $date->format('Y-m-d'),
                    'user_id'  => $user->id,
                ]);
                switch ($request->get('presence_'.$user->id)) {
                    case 'presence':
                        $presence->presence = true;
                        $presence->online = false;
                        $presence->excused = false;
                        break;
                    case 'online':
                        $presence->presence = true;
                        $presence->online = true;
                        break;
                    case 'excused':
                        $presence->excused = true;
                        $presence->presence = false;
                        $presence->online = false;
                        break;
                }
                $presence->created_by = auth()->id();
                $presence->created_at = now();
                $presence->save();

            }

        }

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Anwesenheit wurde gespeichert',
        ]);

    }

    public function addGuest(Request $request, $groupname)
    {
         $request->validate([
            'guest_name' => 'required|string|max:255',
        ]);

        $group = Group::where('name', $groupname)->first();
        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $date = Carbon::now();


        $presence = Presence::firstOrNew([
            'group_id' => $group->id,
            'date'     => $date->format('Y-m-d'),
            'user_id'  => null,
            'guest_name' => $request->guest_name,
            'created_by' => auth()->id(),
        ]);
        $presence->save();

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Gast wurde hinzugefügt',
        ]);

    }
    public function deleteGuest($groupname, Presence $presence)
    {
        $group = Group::where('name', $groupname)->first();
        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($presence->group_id != $group->id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($presence->user_id != null) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Gast',
            ]);
        }

        if ($presence->created_by != auth()->id()) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diesen Gast',
            ]);
        }

        if ($presence->created_at->diffInDays(now()) > 1) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Gast kann nur am selben Tag gelöscht werden',
            ]);
        }

        $presence->delete();

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Gast wurde gelöscht',
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(Presence $presence)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Presence $presence)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Presence $presence)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Presence $presence)
    {
        //
    }
}
