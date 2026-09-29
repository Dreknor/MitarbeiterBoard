<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMeetingRequest;
use App\Models\Meeting;
use App\Models\MeetingTask;
use App\Models\Room;
use App\Models\Type;
use App\Services\Meetings\MeetingService;
use App\Services\Meetings\UpdateMeetingWithRoomBookingAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Detailseite eines Meetings (gruppengebunden oder frei): Agenda,
 * Teilnehmende, Rollen, Einladung, Bearbeiten, Absagen, Löschen.
 */
class MeetingDetailController extends Controller
{
    public function __construct(private readonly MeetingService $meetings)
    {
    }

    public function show(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $user = auth()->user();

        $meeting->load([
            'group',
            'creator',
            'roomBooking.room',
            'invitationSender',
            'meetingTasks.user',
            'participantUsers',
            'participantGroups',
            'participantRoles',
            'themes' => fn ($q) => $q->with(['type', 'group', 'ersteller', 'priorities'])->withCount(['protocols', 'tasks']),
        ]);

        $meetingStart = \Carbon\Carbon::parse($meeting->start_time);
        $meetingEnd   = \Carbon\Carbon::parse($meeting->end_time);

        // Protokolleinträge des Meeting-Tages je Thema (für den "protokolliert"-Status)
        $protokolliertIds = \App\Models\Protocol::query()
            ->whereIn('theme_id', $meeting->themes->pluck('id'))
            ->whereDate('created_at', $meeting->date)
            ->pluck('theme_id')
            ->unique();

        $participants = $meeting->resolvedParticipants();

        return view('meetings.show', [
            'meeting'          => $meeting,
            'agenda'           => $meeting->themes->sortByDesc('priority')->values(),
            'protokolliertIds' => $protokolliertIds,
            'meetingDuration'  => $meetingStart->diffInMinutes($meetingEnd),
            'themesDuration'   => (int) $meeting->themes->sum('duration'),
            'participants'     => $participants,
            'canManage'        => $user->can('manage', $meeting),
            'assignableThemes' => $this->meetings->assignableThemes($meeting, $user),
            'types'            => Type::all(),
            'themeGroups'      => $this->meetings->themeGroupOptions($meeting),
            'options'          => $this->meetings->participantOptions(),
            'selection'        => $this->meetings->participantSelection($meeting),
            'bookableRooms'    => Room::query()->where('bookable', true)->orderBy('room_number')->orderBy('name')->get(),
            'canBookRooms'     => $user->canAny(['create roomBooking', 'manage rooms']),
            'isLive'           => $this->isLive($meeting),
        ]);
    }

    public function update(SaveMeetingRequest $request, Meeting $meeting, UpdateMeetingWithRoomBookingAction $action): RedirectResponse
    {
        $this->authorize('manage', $meeting);

        try {
            DB::transaction(function () use ($action, $meeting, $request) {
                $action->execute($meeting, $request->validated(), auth()->user());
                $this->meetings->syncParticipants($meeting, $request->participantData());
            });
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->with([
                'type'    => 'warning',
                'Meldung' => 'Meeting konnte nicht bearbeitet werden. ' . collect($e->errors())->flatten()->first(),
            ])->withErrors($e->errors());
        }

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Meeting wurde aktualisiert.',
        ]);
    }

    public function cancel(Meeting $meeting): RedirectResponse
    {
        $this->authorize('manage', $meeting);

        $this->meetings->cancel($meeting, auth()->user());

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Meeting wurde abgesagt, eine Raumbuchung wurde freigegeben.',
        ]);
    }

    public function reactivate(Meeting $meeting): RedirectResponse
    {
        $this->authorize('manage', $meeting);

        $this->meetings->reactivate($meeting);

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Meeting wurde wieder aktiviert.',
        ]);
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->authorize('manage', $meeting);

        $this->meetings->delete($meeting);

        return redirect()->route('meetings.overview')->with([
            'type'    => 'success',
            'Meldung' => 'Meeting wurde gelöscht. Die Themen bleiben erhalten.',
        ]);
    }

    public function invite(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('manage', $meeting);

        $request->validate(['message' => ['nullable', 'string', 'max:2000']]);

        $result = $this->meetings->sendInvitations($meeting, $request->input('message'), auth()->user());

        return redirect()->route('meetings.show', $meeting)->with($this->meetings->invitationFlash($result));
    }

    public function storeTask(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('contribute', $meeting);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['required', 'string', 'max:255'],
            'notes'   => ['nullable', 'string', 'max:255'],
        ]);

        $meeting->meetingTasks()->create($validated);

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Rolle wurde vergeben.',
        ]);
    }

    public function destroyTask(Meeting $meeting, MeetingTask $task): RedirectResponse
    {
        $this->authorize('contribute', $meeting);
        abort_unless((int) $task->meeting_id === (int) $meeting->id, 404);

        $task->delete();

        return redirect()->route('meetings.show', $meeting)->with([
            'type'    => 'success',
            'Meldung' => 'Rolle wurde entfernt.',
        ]);
    }

    private function isLive(Meeting $meeting): bool
    {
        if ($meeting->cancelled || ! $meeting->date->isSameDay(now())) {
            return false;
        }

        $date = $meeting->date->format('Y-m-d');

        return now()->between(
            \Carbon\Carbon::parse($date . ' ' . $meeting->start_time),
            \Carbon\Carbon::parse($date . ' ' . $meeting->end_time)
        );
    }
}
