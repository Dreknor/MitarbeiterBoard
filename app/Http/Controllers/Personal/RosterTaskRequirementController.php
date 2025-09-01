<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\personal\RosterTaskRequirement;
use App\Models\personal\Roster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class RosterTaskRequirementController extends Controller
{
    public function store(Request $request, Roster $roster)
    {
        $this->authorize('create roster');
        $data = $request->validate([
            'event_name' => 'required|string|max:120',
            'required_start' => 'nullable|date_format:H:i',
            'required_end' => 'nullable|date_format:H:i',
            'adjust_working_time' => 'nullable|boolean'
        ]);
        $data['department_id'] = $roster->department_id;
        $data['adjust_working_time'] = $request->boolean('adjust_working_time');
        RosterTaskRequirement::create($data);
        return Redirect::route('roster.autoPlan', $roster->id)->with('success','Anforderung gespeichert');
    }

    public function update(Request $request, RosterTaskRequirement $requirement)
    {
        $this->authorize('create roster');
        $data = $request->validate([
            'event_name' => 'required|string|max:120',
            'required_start' => 'nullable|date_format:H:i',
            'required_end' => 'nullable|date_format:H:i',
            'adjust_working_time' => 'nullable|boolean'
        ]);
        $data['adjust_working_time'] = $request->boolean('adjust_working_time');
        $requirement->update($data);
        return back()->with('success','Anforderung aktualisiert');
    }

    public function destroy(RosterTaskRequirement $requirement)
    {
        $this->authorize('create roster');
        $requirement->delete();
        return back()->with('success','Anforderung gelöscht');
    }
}

