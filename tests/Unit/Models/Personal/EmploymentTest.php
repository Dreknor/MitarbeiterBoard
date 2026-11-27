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

class EmploymentTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_employment_hat_employe_relation(): void
    {
        $user       = User::factory()->create();
        $dept       = Group::factory()->asDepartment()->create();
        $employment = Employment::factory()
            ->for($user, 'employe')
            ->for($dept, 'department')
            ->create();

        $this->assertEquals($user->id, $employment->employe->id);
    }

    public function test_employment_hat_department_relation(): void
    {
        $dept       = Group::factory()->asDepartment()->create();
        $employment = Employment::factory()->for($dept, 'department')->create();

        $this->assertEquals($dept->id, $employment->department->id);
    }

    public function test_employment_hat_hour_type_relation(): void
    {
        $ht         = HourType::factory()->create(['fulltimehours' => 40]);
        $employment = Employment::factory()->create(['hour_type_id' => $ht->id, 'hours' => 40]);

        $this->assertEquals($ht->id, $employment->hour_type->id);
    }

    // ─── scopeActive ─────────────────────────────────────────────────────────

    public function test_scopeActive_gibt_aktive_anstellung_zurueck(): void
    {
        $user = User::factory()->create();
        $dept = Group::factory()->asDepartment()->create();
        $ht   = HourType::factory()->create();

        Employment::factory()->for($user, 'employe')->for($dept, 'department')->active()->create([
            'hour_type_id' => $ht->id,
        ]);

        $result = Employment::active()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopeActive_schliesst_beendete_anstellungen_aus(): void
    {
        $user = User::factory()->create();
        $dept = Group::factory()->asDepartment()->create();
        $ht   = HourType::factory()->create();

        // scopeActive() prüft status='aktiv' (nicht mehr end IS NULL – Konzept P1-03)
        Employment::factory()->for($user, 'employe')->for($dept, 'department')->beendet()->create([
            'hour_type_id' => $ht->id,
        ]);

        $result = Employment::active()->get();
        $this->assertCount(0, $result);
    }

    // ─── percent Accessor ────────────────────────────────────────────────────

    public function test_percent_accessor_berechnet_korrekt_bei_vollzeit(): void
    {
        $ht         = HourType::factory()->create(['fulltimehours' => 40]);
        $employment = Employment::factory()->create([
            'hour_type_id' => $ht->id,
            'hours'        => 40,
        ]);

        $this->assertEquals(100.0, $employment->percent);
    }

    public function test_percent_accessor_berechnet_korrekt_bei_teilzeit(): void
    {
        $ht         = HourType::factory()->create(['fulltimehours' => 40]);
        $employment = Employment::factory()->create([
            'hour_type_id' => $ht->id,
            'hours'        => 20,
        ]);

        $this->assertEquals(50.0, $employment->percent);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_employment_start_ist_date(): void
    {
        $employment = Employment::factory()->create(['start' => '2026-01-01']);
        $this->assertInstanceOf(Carbon::class, $employment->start);
    }
}
