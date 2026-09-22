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
class MeetingTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_meeting_hat_group_relation(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create();

        $this->assertEquals($group->id, $meeting->group->id);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_meeting_cancelled_ist_boolean(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->cancelled()->for($group)->create();

        $this->assertIsBool($meeting->cancelled);
        $this->assertTrue($meeting->cancelled);
    }

    public function test_meeting_date_ist_date(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create(['date' => '2026-03-15']);

        $this->assertInstanceOf(Carbon::class, $meeting->date);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function test_scopeUpcoming_gibt_zukunftige_meetings(): void
    {
        $group = Group::factory()->asMeetingGroup()->create();

        Meeting::factory()->upcoming()->for($group)->create();
        Meeting::factory()->past()->for($group)->create();

        $result = Meeting::upcoming()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopePast_gibt_vergangene_meetings(): void
    {
        $group = Group::factory()->asMeetingGroup()->create();

        Meeting::factory()->upcoming()->for($group)->create();
        Meeting::factory()->past()->for($group)->create();

        $result = Meeting::past()->get();
        $this->assertCount(1, $result);
    }
}

/**
 * Tests für das Task-Model (2.6)
 */
class TaskTest extends TestCase
{
    // ─── Polymorphe Relation ──────────────────────────────────────────────────

    public function test_task_hat_polymorphe_taskable_relation_mit_user(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'taskable')->create();

        $this->assertEquals($user->id, Task::withoutGlobalScopes()->find($task->id)->taskable->id);
        $this->assertInstanceOf(User::class, Task::withoutGlobalScopes()->find($task->id)->taskable);
    }

    public function test_task_hat_polymorphe_taskable_relation_mit_group(): void
    {
        $group = Group::factory()->create();
        $task  = Task::factory()->for($group, 'taskable')->create();

        $this->assertEquals($group->id, Task::withoutGlobalScopes()->find($task->id)->taskable->id);
        $this->assertInstanceOf(Group::class, Task::withoutGlobalScopes()->find($task->id)->taskable);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_task_completed_ist_boolean(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->completed()->for($user, 'taskable')->create();

        $this->assertIsBool(Task::withoutGlobalScopes()->find($task->id)->completed);
        $this->assertTrue(Task::withoutGlobalScopes()->find($task->id)->completed);
    }

    // ─── GlobalScope ─────────────────────────────────────────────────────────

    public function test_task_global_scope_schliesst_completed_aus(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'taskable')->create(['completed' => false]);
        Task::factory()->completed()->for($user, 'taskable')->create();

        // Standard-Query (mit globalem Scope) gibt nur nicht-abgeschlossene zurück
        $this->assertCount(1, Task::all());
    }

    public function test_task_ohne_global_scope_zeigt_alle(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'taskable')->create(['completed' => false]);
        Task::factory()->completed()->for($user, 'taskable')->create();

        $this->assertCount(2, Task::withoutGlobalScopes()->get());
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_task_wird_soft_deleted(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user, 'taskable')->create();
        $id   = $task->id;

        $task->delete();

        // Normales find() wendet SoftDeletingScope an → nicht auffindbar
        $this->assertNull(Task::find($id));
        // withoutGlobalScopes() entfernt ALLE Scopes inkl. SoftDeletes → sichtbar
        $this->assertNotNull(Task::withoutGlobalScopes()->find($id));
    }
}

/**
 * Tests für das Ticket-Model (2.6)
 */
class TicketTest extends TestCase
{
    private function createTicket(array $attrs = []): Ticket
    {
        $user = User::factory()->create();
        $cat  = TicketCategory::factory()->create();
        return Ticket::factory()->for($user, 'user')->for($cat, 'category')->create($attrs);
    }

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_ticket_hat_user_relation(): void
    {
        $ticket = $this->createTicket();
        $this->assertInstanceOf(User::class, $ticket->user);
    }

    public function test_ticket_hat_category_relation(): void
    {
        $ticket = $this->createTicket();
        $this->assertInstanceOf(TicketCategory::class, $ticket->category);
    }

    public function test_ticket_hat_comments_relation(): void
    {
        $ticket  = $this->createTicket();
        $author  = User::factory()->create();
        TicketComment::factory()->for($ticket)->for($author, 'user')->create();

        $this->assertCount(1, $ticket->comments);
    }

    public function test_ticket_hat_assigned_relation(): void
    {
        $assignee = User::factory()->create();
        $ticket   = $this->createTicket(['assigned_to' => $assignee->id]);

        $this->assertEquals($assignee->id, $ticket->assigned->id);
    }

    // ─── Status ──────────────────────────────────────────────────────────────

    public function test_ticket_scopeOpen_gibt_offene_tickets(): void
    {
        $user = User::factory()->create();
        $cat  = TicketCategory::factory()->create();

        Ticket::factory()->open()->for($user, 'user')->for($cat, 'category')->create();
        Ticket::factory()->closed()->for($user, 'user')->for($cat, 'category')->create();

        $result = Ticket::open()->get();
        $this->assertCount(1, $result);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_ticket_wird_soft_deleted(): void
    {
        $ticket = $this->createTicket();
        $id     = $ticket->id;

        $ticket->delete();

        $this->assertNull(Ticket::find($id));
        $this->assertNotNull(Ticket::withTrashed()->find($id));
    }
}

/**
 * Tests für das Schueler-Model (2.6)
 */
class SchuelerTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_schueler_hat_klasse_relation(): void
    {
        $klasse   = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $this->assertEquals($klasse->id, $schueler->klasse->id);
    }

    public function test_schueler_hat_wp_plaene_relation(): void
    {
        $schueler  = Schueler::factory()->create();
        $parent    = \App\Models\Wochenplan\WpPlan::factory()->alsKlassenplan()->create();
        \App\Models\Wochenplan\WpPlan::factory()
            ->alsSchuelerplan($parent)
            ->create(['schueler_id' => $schueler->id]);

        $this->assertCount(1, $schueler->wpPlaene);
    }

    // ─── Accessor ────────────────────────────────────────────────────────────

    public function test_schueler_name_accessor(): void
    {
        $schueler = Schueler::factory()->create([
            'vorname'  => 'Max',
            'nachname' => 'Muster',
        ]);

        $this->assertEquals('Max Muster', $schueler->name);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_schueler_wird_soft_deleted(): void
    {
        $schueler = Schueler::factory()->create();
        $id       = $schueler->id;

        $schueler->delete();

        $this->assertNull(Schueler::find($id));
        $this->assertNotNull(Schueler::withTrashed()->find($id));
    }
}

/**
 * Tests für das Room/RoomBooking-Model (2.6)
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


