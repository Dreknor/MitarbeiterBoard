<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\Room;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Tests\TestCase;

class MeetingRoomBookingTest extends TestCase
{
    private function createMeetingGroupForUser(array $permissions = []): array
    {
        $user = $this->actingAsWithPermission(...$permissions);
        $group = Group::factory()->asMeetingGroup()->create([
            'name' => 'Gruppe-' . uniqid(),
        ]);
        $group->users()->attach($user->id);

        return [$user, $group];
    }

    /** @test */
    public function meeting_can_be_created_with_room_booking(): void
    {
        [, $group] = $this->createMeetingGroupForUser(['view roomBooking', 'create roomBooking']);
        $room = Room::factory()->create(['bookable' => true]);

        $payload = [
            'title' => 'Dienstberatung',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'book_room' => '1',
            'room_id' => $room->id,
        ];

        $this->post(route('meetings.store', ['group' => $group->name]), $payload)
            ->assertRedirect(route('meetings.index', ['group' => $group->name]));

        $meeting = Meeting::where('title', 'Dienstberatung')->first();
        $this->assertNotNull($meeting);

        $this->assertDatabaseHas('room_bookings', [
            'meeting_id' => $meeting->id,
            'room_id' => $room->id,
            'is_recurring' => false,
            'cancelled' => false,
        ]);
    }

    /** @test */
    public function meeting_creation_is_blocked_on_room_collision(): void
    {
        [, $group] = $this->createMeetingGroupForUser(['view roomBooking', 'create roomBooking']);
        $room = Room::factory()->create(['bookable' => true]);
        $date = now()->addDays(2);

        RoomBooking::factory()->oneTime()->create([
            'room_id' => $room->id,
            'booking_date' => $date,
            'start' => '09:00',
            'end' => '10:00',
            'is_recurring' => false,
            'cancelled' => false,
            'source' => 'manual',
            'meeting_id' => null,
        ]);

        $response = $this->from(route('meetings.index', ['group' => $group->name]))
            ->post(route('meetings.store', ['group' => $group->name]), [
                'title' => 'Kollisionstest',
                'date' => $date->format('Y-m-d'),
                'start_time' => '09:15',
                'end_time' => '09:45',
                'book_room' => '1',
                'room_id' => $room->id,
            ]);

        $response->assertRedirect(route('meetings.index', ['group' => $group->name]));
        $response->assertSessionHasErrors('room_id');
        $this->assertDatabaseMissing('meetings', ['title' => 'Kollisionstest']);
    }

    /** @test */
    public function meeting_update_changes_existing_room_booking(): void
    {
        [, $group] = $this->createMeetingGroupForUser(['view roomBooking', 'create roomBooking']);
        $roomA = Room::factory()->create(['bookable' => true]);
        $roomB = Room::factory()->create(['bookable' => true]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'Update Meeting',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        RoomBooking::factory()->create([
            'meeting_id' => $meeting->id,
            'room_id' => $roomA->id,
            'start' => '09:00',
            'end' => '10:00',
            'is_recurring' => false,
            'booking_date' => Carbon::parse($meeting->date),
            'cancelled' => false,
        ]);

        $this->put(route('meetings.update', ['group' => $group->name, 'meeting' => $meeting->id]), [
            'title' => 'Update Meeting Neu',
            'date' => Carbon::parse($meeting->date)->format('Y-m-d'),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'book_room' => '1',
            'room_id' => $roomB->id,
        ])->assertRedirect(route('meetings.index', ['group' => $group->name]));

        $this->assertDatabaseHas('room_bookings', [
            'meeting_id' => $meeting->id,
            'room_id' => $roomB->id,
            'start' => '11:00',
            'end' => '12:00',
            'cancelled' => false,
        ]);
    }

    /** @test */
    public function room_booking_can_be_removed_on_meeting_update(): void
    {
        [, $group] = $this->createMeetingGroupForUser(['view roomBooking', 'create roomBooking']);
        $room = Room::factory()->create(['bookable' => true]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'Meeting ohne Raum',
            'date' => now()->addDays(4)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $booking = RoomBooking::factory()->create([
            'meeting_id' => $meeting->id,
            'room_id' => $room->id,
            'is_recurring' => false,
            'booking_date' => Carbon::parse($meeting->date),
            'cancelled' => false,
        ]);

        $this->put(route('meetings.update', ['group' => $group->name, 'meeting' => $meeting->id]), [
            'title' => $meeting->title,
            'date' => Carbon::parse($meeting->date)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'book_room' => '0',
        ])->assertRedirect(route('meetings.index', ['group' => $group->name]));

        $booking->refresh();
        $this->assertTrue((bool) $booking->cancelled);
    }

    /** @test */
    public function meeting_cancellation_frees_linked_room_booking(): void
    {
        [, $group] = $this->createMeetingGroupForUser(['view roomBooking', 'create roomBooking']);
        $room = Room::factory()->create(['bookable' => true]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $booking = RoomBooking::factory()->create([
            'meeting_id' => $meeting->id,
            'room_id' => $room->id,
            'is_recurring' => false,
            'booking_date' => Carbon::parse($meeting->date),
            'cancelled' => false,
        ]);

        $this->post(route('meetings.cancel', ['group' => $group->name, 'meeting' => $meeting->id]))
            ->assertRedirect(route('meetings.index', ['group' => $group->name]));

        $booking->refresh();
        $this->assertTrue((bool) $booking->cancelled);
    }

    /** @test */
    public function room_availability_endpoint_returns_conflict_and_alternatives(): void
    {
        [, $group] = $this->createMeetingGroupForUser(['view roomBooking']);

        $roomMain = Room::factory()->create(['bookable' => true]);
        $roomAlt = Room::factory()->create(['bookable' => true]);
        $date = now()->addDays(6)->format('Y-m-d');

        RoomBooking::factory()->oneTime()->create([
            'room_id' => $roomMain->id,
            'booking_date' => Carbon::parse($date),
            'start' => '08:00',
            'end' => '09:30',
            'is_recurring' => false,
            'cancelled' => false,
        ]);

        $response = $this->get(route('rooms.availability', ['room' => $roomMain->id]) . '?date=' . $date . '&start_time=08:30&end_time=09:00');

        $response->assertOk();
        $response->assertJsonPath('available', false);
        $response->assertJsonPath('alternatives.0.id', $roomAlt->id);
    }
}

