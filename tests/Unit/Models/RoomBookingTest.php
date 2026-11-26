<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\Klasse;
use App\Models\Meeting;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Schueler;
use App\Models\Task;
use App\Models\Theme;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Vertretung;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests für das Meeting-Model (2.6)
 */
class RoomBookingTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_room_booking_hat_room_relation(): void
    {
        $room    = Room::factory()->create();
        $user    = User::factory()->create();
        $booking = RoomBooking::factory()->for($room, 'room')->for($user, 'user')->create();

        $this->assertEquals($room->id, $booking->room->id);
    }

    public function test_room_booking_hat_user_relation(): void
    {
        $room    = Room::factory()->create();
        $user    = User::factory()->create();
        $booking = RoomBooking::factory()->for($room, 'room')->for($user, 'user')->create();

        $this->assertEquals($user->id, $booking->user->id);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function test_scopeActive_schliesst_stornierte_aus(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();

        RoomBooking::factory()->for($room, 'room')->for($user, 'user')->create(['cancelled' => false]);
        RoomBooking::factory()->for($room, 'room')->for($user, 'user')->create(['cancelled' => true]);

        $result = RoomBooking::active()->get();
        $this->assertCount(1, $result);
    }

    // ─── Duration Accessor ───────────────────────────────────────────────────

    public function test_room_booking_duration_berechnet_minuten(): void
    {
        $room    = Room::factory()->create();
        $user    = User::factory()->create();
        $booking = RoomBooking::factory()->for($room, 'room')->for($user, 'user')->create([
            'start' => '08:00',
            'end'   => '09:30',
        ]);

        $this->assertEquals(90, $booking->duration);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_room_booking_cancelled_ist_boolean(): void
    {
        $room    = Room::factory()->create();
        $user    = User::factory()->create();
        $booking = RoomBooking::factory()->for($room, 'room')->for($user, 'user')->create([
            'cancelled' => true,
        ]);

        $this->assertIsBool($booking->cancelled);
    }
}
