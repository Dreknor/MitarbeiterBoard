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
class RoomTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_room_hat_bookings_relation(): void
    {
        $room    = Room::factory()->create();
        $user    = User::factory()->create();
        RoomBooking::factory()->for($room, 'room')->for($user, 'user')->create();

        $this->assertCount(1, $room->bookings);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_room_bookable_ist_boolean(): void
    {
        $room = Room::factory()->create(['bookable' => true]);
        $this->assertIsBool($room->bookable);
        $this->assertTrue($room->bookable);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_room_wird_soft_deleted(): void
    {
        $room = Room::factory()->create();
        $id   = $room->id;

        $room->delete();

        $this->assertNull(Room::find($id));
        $this->assertNotNull(Room::withTrashed()->find($id));
    }
}

/**
 * Tests für das RoomBooking-Model (2.6)
 */
