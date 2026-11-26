<?php

namespace Tests\Unit\Models\Personal;

use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\personal\Holiday;
use App\Models\personal\HourType;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\personal\WorkingTime;
use App\Models\personal\EmployeHolidayClaim;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class RosterEventsTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_roster_events_hat_roster_relation(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->create();
        $event  = RosterEvents::factory()->for($roster)->create();

        $this->assertEquals($roster->id, $event->roster->id);
    }

    public function test_roster_events_hat_employe_relation(): void
    {
        $user  = User::factory()->create();
        $event = RosterEvents::factory()->for($user, 'employe')->create();

        $this->assertEquals($user->id, $event->employe->id);
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function test_roster_event_duration_berechnet_minuten(): void
    {
        $event = RosterEvents::factory()->create([
            'date'  => '2026-03-09',
            'start' => '09:00:00',
            'end'   => '10:00:00',
        ]);

        $this->assertEquals(60, $event->duration);
    }

    public function test_roster_event_start_wird_als_carbon_geparst(): void
    {
        $event = RosterEvents::factory()->create([
            'date'  => '2026-03-09',
            'start' => '09:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $event->start);
    }
}
