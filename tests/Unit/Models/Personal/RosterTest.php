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

class RosterTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_roster_hat_department_relation(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->create();

        $this->assertEquals($dept->id, $roster->department->id);
    }

    public function test_roster_hat_working_times_relation(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->create();
        WorkingTime::factory()->for($roster)->create();

        $this->assertCount(1, $roster->working_times);
    }

    public function test_roster_hat_events_relation(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->create();
        RosterEvents::factory()->for($roster)->create();

        $this->assertCount(1, $roster->events);
    }

    // ─── published Flag ──────────────────────────────────────────────────────

    public function test_roster_published_flag(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->published()->create();

        $this->assertTrue($roster->published);
    }

    public function test_roster_is_template_accessor(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->create(['type' => 'template']);

        $this->assertTrue($roster->is_template);
    }

    public function test_roster_is_not_template_bei_normalem_typ(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->create(['type' => 'weekly']);

        $this->assertFalse($roster->is_template);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_roster_start_date_ist_datetime(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        $roster = Roster::factory()->for($dept, 'department')->create(['start_date' => '2026-03-09']);
        $this->assertInstanceOf(Carbon::class, $roster->start_date);
    }
}
