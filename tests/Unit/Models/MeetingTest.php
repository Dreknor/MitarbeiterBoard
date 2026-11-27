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
