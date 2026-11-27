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

class WorkingTimeTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_working_time_hat_employe_relation(): void
    {
        $user        = User::factory()->create();
        $workingTime = WorkingTime::factory()->for($user, 'employe')->create();

        $this->assertEquals($user->id, $workingTime->employe->id);
    }

    public function test_working_time_hat_roster_relation(): void
    {
        $dept        = Group::factory()->asDepartment()->create();
        $roster      = Roster::factory()->for($dept, 'department')->create();
        $workingTime = WorkingTime::factory()->for($roster)->create();

        $this->assertEquals($roster->id, $workingTime->roster->id);
    }

    // ─── Casts & Accessors ───────────────────────────────────────────────────

    public function test_working_time_start_wird_als_carbon_geparst(): void
    {
        $workingTime = WorkingTime::factory()->create([
            'date'  => '2026-03-09',
            'start' => '08:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $workingTime->start);
        $this->assertEquals('08:00', $workingTime->start->format('H:i'));
    }

    public function test_working_time_end_wird_als_carbon_geparst(): void
    {
        $workingTime = WorkingTime::factory()->create([
            'date' => '2026-03-09',
            'end'  => '16:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $workingTime->end);
        $this->assertEquals('16:00', $workingTime->end->format('H:i'));
    }

    public function test_working_time_duration_berechnet_minuten(): void
    {
        $workingTime = WorkingTime::factory()->create([
            'date'  => '2026-03-09',
            'start' => '08:00:00',
            'end'   => '16:00:00',
        ]);

        $this->assertEquals(480, $workingTime->duration); // 8 Stunden = 480 Minuten
    }

    public function test_working_time_needs_break_ueber_6_stunden(): void
    {
        $workingTime = WorkingTime::factory()->create([
            'date'  => '2026-03-09',
            'start' => '08:00:00',
            'end'   => '16:00:00',
        ]);

        $this->assertTrue($workingTime->needs_break());
    }

    public function test_working_time_needs_no_break_unter_6_stunden(): void
    {
        $workingTime = WorkingTime::factory()->create([
            'date'  => '2026-03-09',
            'start' => '08:00:00',
            'end'   => '12:00:00',
        ]);

        $this->assertFalse($workingTime->needs_break());
    }
}
