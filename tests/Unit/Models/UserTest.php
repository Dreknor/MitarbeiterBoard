<?php

namespace Tests\Unit\Models;

use App\Models\Absence;
use App\Models\Group;
use App\Models\personal\EmployeData;
use App\Models\personal\Employment;
use App\Models\personal\Holiday;
use App\Models\personal\HourType;
use App\Models\personal\RosterEvents;
use App\Models\personal\Timesheet;
use App\Models\personal\WorkingTime;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Klasse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

class UserTest extends TestCase
{
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_user_hat_employe_data_relation(): void
    {
        $user = User::factory()->create();
        EmployeData::create([
            'user_id'       => $user->id,
            'familienname'  => 'Mustermann',
            'vorname'       => 'Max',
        ]);

        $this->assertInstanceOf(EmployeData::class, $user->employe_data);
        $this->assertEquals('Mustermann', $user->employe_data->familienname);
    }

    public function test_user_hat_employments_relation(): void
    {
        $user  = User::factory()->create();
        $dept  = Group::factory()->asDepartment()->create();
        $ht    = HourType::factory()->create();

        Employment::factory()->for($user, 'employe')->for($dept, 'department')->create([
            'hour_type_id' => $ht->id,
        ]);

        $this->assertCount(1, $user->employments);
    }

    public function test_user_hat_holidays_relation(): void
    {
        $user = User::factory()->create();
        Holiday::factory()->for($user, 'employe')->create();

        $this->assertCount(1, $user->holidays);
    }

    public function test_user_hat_groups_rel_relation(): void
    {
        $user  = User::factory()->create();
        $group = Group::factory()->create();
        $user->groups_rel()->attach($group);

        $this->assertCount(1, $user->groups_rel);
    }

    public function test_user_hat_working_times_relation(): void
    {
        $user = User::factory()->create();
        WorkingTime::factory()->for($user, 'employe')->create();

        $this->assertCount(1, $user->working_times);
    }

    public function test_user_hat_roster_events_relation(): void
    {
        $user = User::factory()->create();
        RosterEvents::factory()->for($user, 'employe')->create();

        $this->assertCount(1, $user->roster_events);
    }

    public function test_user_hat_tasks_relation(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user, 'taskable')->create();

        $this->assertCount(1, $user->tasks);
    }

    public function test_user_hat_tickets_relation(): void
    {
        $user   = User::factory()->create();
        $cat    = \App\Models\TicketCategory::factory()->create();
        Ticket::factory()->for($user, 'user')->for($cat, 'category')->create();

        $this->assertCount(1, $user->tickets);
    }

    public function test_user_hat_absences_relation(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        Absence::factory()->create([
            'users_id'   => $user->id,
            'creator_id' => $admin->id,
        ]);

        $this->assertCount(1, $user->absences);
    }

    public function test_user_hat_timesheets_relation(): void
    {
        $user = User::factory()->create();
        Timesheet::factory()->for($user, 'employe')->create();

        $this->assertCount(1, $user->timesheets);
    }

    public function test_user_hat_paed_klassen_relation(): void
    {
        $user   = User::factory()->create();
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse);

        $this->assertCount(1, $user->paed_klassen);
    }

    public function test_user_hat_superior_relation(): void
    {
        $chef       = User::factory()->create();
        $mitarbeiter = User::factory()->create(['superior_id' => $chef->id]);

        $this->assertEquals($chef->id, $mitarbeiter->superior->id);
    }

    public function test_user_hat_subordinates_relation(): void
    {
        $chef = User::factory()->create();
        User::factory()->create(['superior_id' => $chef->id]);
        User::factory()->create(['superior_id' => $chef->id]);

        $this->assertCount(2, $chef->subordinates);
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function test_geburtstag_accessor_delegiert_an_employe_data(): void
    {
        $user = User::factory()->create();
        EmployeData::create([
            'user_id'    => $user->id,
            'geburtstag' => '1990-05-15',
        ]);

        $user->refresh();
        $this->assertEquals('1990-05-15', $user->geburtstag->format('Y-m-d'));
    }

    public function test_geburtstag_accessor_gibt_null_ohne_employe_data(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->geburtstag);
    }

    public function test_shortname_accessor_verwendet_employe_data(): void
    {
        $user = User::factory()->create(['name' => 'Max Mustermann']);
        EmployeData::create([
            'user_id'      => $user->id,
            'familienname' => 'Mustermann',
            'vorname'      => 'Max',
        ]);

        $user->refresh();
        $this->assertStringContainsString('Mustermann', $user->shortname);
    }

    public function test_vorname_accessor(): void
    {
        $user = User::factory()->create(['name' => 'Max Mustermann']);
        $this->assertEquals('Max', $user->vorname);
    }

    public function test_familienname_accessor(): void
    {
        $user = User::factory()->create(['name' => 'Max Mustermann']);
        $this->assertEquals('Mustermann', $user->familienname);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_absence_abo_daily_ist_boolean(): void
    {
        $user = User::factory()->create(['absence_abo_daily' => true]);
        $this->assertIsBool($user->absence_abo_daily);
        $this->assertTrue($user->absence_abo_daily);
    }

    public function test_email_verified_at_ist_datetime(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_user_wird_soft_deleted(): void
    {
        $user = User::factory()->create();
        $id   = $user->id;

        $user->delete();

        $this->assertNull(User::find($id));
        $this->assertNotNull(User::withTrashed()->find($id));
    }

    public function test_user_ist_wiederherstellbar(): void
    {
        $user = User::factory()->create();
        $id   = $user->id;

        $user->delete();
        User::withTrashed()->find($id)->restore();

        $this->assertNotNull(User::find($id));
    }

    // ─── is_supervisor ───────────────────────────────────────────────────────

    public function test_is_supervisor_of_gibt_true_fuer_untergeordneten(): void
    {
        $chef        = User::factory()->create();
        $mitarbeiter = User::factory()->create(['superior_id' => $chef->id]);

        $this->assertTrue($chef->isSupervisorOf($mitarbeiter));
    }

    public function test_is_supervisor_of_gibt_false_fuer_fremden(): void
    {
        $chef   = User::factory()->create();
        $fremd  = User::factory()->create();

        $this->assertFalse($chef->isSupervisorOf($fremd));
    }

    // ─── hasHoliday ──────────────────────────────────────────────────────────

    public function test_hasHoliday_gibt_true_wenn_urlaub_existiert(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $user = User::factory()->create();
        Holiday::factory()->for($user, 'employe')->create([
            'start_date' => '2026-03-10',
            'end_date'   => '2026-03-14',
            'approved'   => true,
            'rejected'   => false,
        ]);

        // hasHoliday prüft ob Holiday-Start ODER -End in den angefragten Zeitraum fällt
        $this->assertTrue($user->hasHoliday(
            Carbon::parse('2026-03-10'),
            Carbon::parse('2026-03-14')
        ));
    }

    public function test_hasHoliday_gibt_false_ohne_urlaub(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasHoliday(Carbon::parse('2026-03-11')));
    }

    // ─── hasAbsence ──────────────────────────────────────────────────────────

    public function test_hasAbsence_gibt_true_wenn_abwesenheit_existiert(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        Absence::factory()->create([
            'users_id'   => $user->id,
            'creator_id' => $admin->id,
            'start'      => '2026-03-10',
            'end'        => '2026-03-14',
        ]);

        $this->assertTrue($user->hasAbsence(Carbon::parse('2026-03-11')));
    }

    public function test_hasAbsence_gibt_false_ohne_abwesenheit(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasAbsence(Carbon::parse('2026-03-11')));
    }

    // ─── employments_date ────────────────────────────────────────────────────

    public function test_employments_date_filtert_aktive_anstellungen(): void
    {
        $user = User::factory()->create();
        $dept = Group::factory()->asDepartment()->create();
        $ht   = HourType::factory()->create();

        // Aktive Anstellung
        Employment::factory()->for($user, 'employe')->for($dept, 'department')->create([
            'hour_type_id' => $ht->id,
            'start'        => Carbon::now()->subYear(),
            'end'          => null,
        ]);

        // Beendete Anstellung
        Employment::factory()->for($user, 'employe')->for($dept, 'department')->create([
            'hour_type_id' => $ht->id,
            'start'        => Carbon::now()->subYears(3),
            'end'          => Carbon::now()->subYear()->subDay(),
        ]);

        $result = $user->employments_date(Carbon::now());
        $this->assertCount(1, $result);
    }

    // ─── getHolidayClaim ─────────────────────────────────────────────────────

    public function test_getHolidayClaim_aus_setting(): void
    {
        // Die Migration legt holiday_claim=28 an – wir aktualisieren auf 30
        \App\Models\Setting::where('setting', 'holiday_claim')->update(['value' => 30]);
        Cache::flush();

        $user = User::factory()->create();
        $this->assertEquals(30, $user->getHolidayClaim(Carbon::now()));
    }
}





