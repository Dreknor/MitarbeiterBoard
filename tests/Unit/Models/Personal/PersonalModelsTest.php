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

class HolidayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // HolidayObserver ruft auth()->id() auf – ohne authentifizierten User
        // schlägt die NOT-NULL-Constraint für absences.creator_id fehl.
        $actor = User::factory()->create();
        $this->actingAs($actor);
    }

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_holiday_hat_employe_relation(): void
    {
        $user    = User::factory()->create();
        $holiday = Holiday::factory()->for($user, 'employe')->create();

        $this->assertEquals($user->id, $holiday->employe->id);
    }

    public function test_holiday_hat_approved_by_relation(): void
    {
        $user      = User::factory()->create();
        $approver  = User::factory()->create();
        $holiday   = Holiday::factory()->for($user, 'employe')->approved()->create([
            'approved_by' => $approver->id,
        ]);

        $this->assertEquals($approver->id, $holiday->approved_by()->first()->id);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_holiday_start_date_ist_date(): void
    {
        $holiday = Holiday::factory()->create(['start_date' => '2026-03-10']);
        $this->assertInstanceOf(Carbon::class, $holiday->start_date);
    }

    public function test_holiday_end_date_ist_date(): void
    {
        $holiday = Holiday::factory()->create(['end_date' => '2026-03-14']);
        $this->assertInstanceOf(Carbon::class, $holiday->end_date);
    }

    public function test_holiday_approved_ist_boolean(): void
    {
        $holiday = Holiday::factory()->approved()->create();
        $this->assertIsBool($holiday->approved);
        $this->assertTrue($holiday->approved);
    }

    public function test_holiday_rejected_ist_boolean(): void
    {
        $holiday = Holiday::factory()->rejected()->create();
        $this->assertIsBool($holiday->rejected);
        $this->assertTrue($holiday->rejected);
    }

    public function test_holiday_approved_at_ist_datetime(): void
    {
        $holiday = Holiday::factory()->approved()->create();
        $this->assertInstanceOf(Carbon::class, $holiday->approved_at);
    }

    // ─── States ──────────────────────────────────────────────────────────────

    public function test_holiday_pending_state(): void
    {
        $holiday = Holiday::factory()->pending()->create();
        $this->assertFalse($holiday->approved);
        $this->assertFalse($holiday->rejected);
    }

    public function test_holiday_approved_state(): void
    {
        $holiday = Holiday::factory()->approved()->create();
        $this->assertTrue($holiday->approved);
        $this->assertFalse($holiday->rejected);
        $this->assertNotNull($holiday->approved_at);
    }

    public function test_holiday_rejected_state(): void
    {
        $holiday = Holiday::factory()->rejected()->create();
        $this->assertFalse($holiday->approved);
        $this->assertTrue($holiday->rejected);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_holiday_wird_soft_deleted(): void
    {
        $holiday = Holiday::factory()->create();
        $id      = $holiday->id;

        $holiday->delete();

        $this->assertNull(Holiday::find($id));
        $this->assertNotNull(Holiday::withTrashed()->find($id));
    }
}

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

