<?php

namespace App\Services\Meetings;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateMeetingWithRoomBookingAction
{
    public function execute(Group $group, array $data, User $user): Meeting
    {
        return DB::transaction(function () use ($group, $data, $user) {
            $meetingData = Arr::only($data, ['title', 'date', 'start_time', 'end_time']);
            $meetingData['group_id'] = $group->id;

            $meeting = Meeting::create($meetingData);

            Log::info('Meeting erstellt', [
                'meeting_id' => $meeting->id,
                'group_id' => $group->id,
                'user_id' => $user->id,
            ]);

            if (!($data['book_room'] ?? false)) {
                return $meeting;
            }

            $this->assertRoomBookingPermission($user);

            $room = Room::query()->where('bookable', true)->findOrFail((int) $data['room_id']);
            $meetingDate = Carbon::parse($meeting->date);

            $collision = $room->hasBookingCollision(
                $meeting->start_time,
                $meeting->end_time,
                $meetingDate->dayOfWeek,
                $meetingDate,
                null
            );

            if ($collision) {
                $suggestionText = $this->buildSuggestionText($room, $meetingDate, $meeting->start_time, $meeting->end_time);

                throw ValidationException::withMessages([
                    'room_id' => 'Der Raum ist im gewählten Zeitraum bereits belegt. ' . $suggestionText,
                ]);
            }

            RoomBooking::create([
                'meeting_id'    => $meeting->id,
                'room_id'       => $room->id,
                'weekday'       => $meetingDate->dayOfWeek,
                'start'         => Carbon::parse($meeting->start_time)->format('H:i'),
                'end'           => Carbon::parse($meeting->end_time)->format('H:i'),
                'name'          => 'Meeting: ' . $meeting->title,
                'users_id'      => $user->id,
                'is_recurring'  => false,
                'booking_date'  => $meetingDate,
                'week'          => null,
                'source'        => 'manual',
                'cancelled'     => false,
            ]);

            Log::info('Raum für Meeting gebucht', [
                'meeting_id' => $meeting->id,
                'room_id' => $room->id,
                'user_id' => $user->id,
            ]);

            return $meeting;
        });
    }

    private function assertRoomBookingPermission(User $user): void
    {
        if (!$user->canAny(['create roomBooking', 'manage rooms'])) {
            throw ValidationException::withMessages([
                'room_id' => 'Für die Raumbuchung fehlt die Berechtigung.',
            ]);
        }
    }

    private function buildSuggestionText(Room $selectedRoom, Carbon $meetingDate, string $start, string $end): string
    {
        $alternativen = Room::query()
            ->where('bookable', true)
            ->where('id', '!=', $selectedRoom->id)
            ->orderBy('room_number')
            ->orderBy('name')
            ->get()
            ->filter(function (Room $room) use ($meetingDate, $start, $end) {
                return $room->hasBookingCollision($start, $end, $meetingDate->dayOfWeek, $meetingDate) === null;
            })
            ->take(3)
            ->pluck('name')
            ->values();

        if ($alternativen->isEmpty()) {
            return 'Es sind keine direkten Alternativräume frei.';
        }

        return 'Freie Alternativen: ' . $alternativen->implode(', ') . '.';
    }
}



