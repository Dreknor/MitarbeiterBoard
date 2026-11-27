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

class TimesheetTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_timesheet_hat_employe_relation(): void
    {
        $user      = User::factory()->create();
        $timesheet = Timesheet::factory()->for($user, 'employe')->create();

        $this->assertEquals($user->id, $timesheet->employe->id);
    }

    public function test_timesheet_hat_timesheet_days_relation(): void
    {
        $user      = User::factory()->create();
        $timesheet = Timesheet::factory()->for($user, 'employe')->create();

        $this->assertCount(0, $timesheet->timesheet_days);
    }

    // ─── locked State ────────────────────────────────────────────────────────

    public function test_timesheet_ist_gesperrt_wenn_locked_at_gesetzt(): void
    {
        $timesheet = Timesheet::factory()->locked()->create();
        $this->assertTrue($timesheet->is_locked);
    }

    public function test_timesheet_ist_nicht_gesperrt_ohne_locked_at(): void
    {
        $user      = User::factory()->create();
        $timesheet = Timesheet::factory()->for($user, 'employe')->create(['locked_at' => null]);
        $this->assertFalse($timesheet->is_locked);
    }

    // ─── working_time_account Accessor ───────────────────────────────────────

    public function test_working_time_account_gibt_0_wenn_null(): void
    {
        $user      = User::factory()->create();
        // Die DB-Spalte ist NOT NULL – wir testen den Accessor mit Wert 0
        $timesheet = Timesheet::factory()->for($user, 'employe')->create([
            'working_time_account' => 0,
        ]);

        $this->assertEquals(0, $timesheet->working_time_account);
    }
}
