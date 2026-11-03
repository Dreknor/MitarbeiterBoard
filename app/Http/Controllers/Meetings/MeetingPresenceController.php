<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Presence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Anwesenheitserfassung eines Meetings (gruppengebunden oder frei).
 */
class MeetingPresenceController extends Controller
{
    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('contribute', $meeting);

        foreach ($meeting->resolvedParticipants() as $user) {
            $status = $request->input('presence_' . $user->id);
            if (! in_array($status, ['presence', 'online', 'excused', 'absent'], true)) {
                continue;
            }

            $presence = Presence::firstOrNew([
                'meeting_id' => $meeting->id,
                'user_id'    => $user->id,
            ]);

            $presence->group_id   = $meeting->group_id;
            $presence->date       = $meeting->date->format('Y-m-d');
            $presence->presence   = in_array($status, ['presence', 'online'], true);
            $presence->online     = $status === 'online';
            $presence->excused    = $status === 'excused';
            $presence->created_by = $presence->created_by ?: auth()->id();
            $presence->save();
        }

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Anwesenheit wurde gespeichert.',
        ]);
    }

    public function addGuest(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('contribute', $meeting);

        $data = $request->validate(['guest_name' => ['required', 'string', 'max:255']]);

        Presence::create([
            'meeting_id' => $meeting->id,
            'group_id'   => $meeting->group_id,
            'date'       => $meeting->date->format('Y-m-d'),
            'user_id'    => null,
            'guest_name' => $data['guest_name'],
            'presence'   => true,
            'created_by' => auth()->id(),
        ]);

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Gast wurde hinzugefügt.',
        ]);
    }

    public function deleteGuest(Meeting $meeting, Presence $presence): RedirectResponse
    {
        $this->authorize('contribute', $meeting);
        abort_unless((int) $presence->meeting_id === (int) $meeting->id && $presence->user_id === null, 404);

        $presence->delete();

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Gast wurde entfernt.',
        ]);
    }
}

