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
