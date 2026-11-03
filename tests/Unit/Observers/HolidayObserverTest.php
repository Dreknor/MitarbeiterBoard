<?php

namespace Tests\Unit\Observers;

use App\Models\Absence;
use App\Models\personal\Holiday;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testet HolidayObserver – kritischer Seiteneffekt:
 * Genehmigter Urlaub → automatisch Absence anlegen/löschen.
 */
class HolidayObserverTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->actingAs($this->actor);
        Cache::flush();
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function settingAktiv(): void
    {
        // Migration create_holidays_table setzt value='1' – das ist bereits der Default
        \App\Models\Setting::where('setting', 'absence_auto_create')->update(['value' => '1']);
        Cache::forget('setting_absence_auto_create');
    }

    private function settingInaktiv(): void
    {
        // Vorhandenen Migrationseintrag auf '0' setzen
        \App\Models\Setting::where('setting', 'absence_auto_create')->update(['value' => '0']);
        Cache::forget('setting_absence_auto_create');
    }

    // ─── created ─────────────────────────────────────────────────────────────

    /** Genehmigter Urlaub + Setting aktiviert → Absence wird erstellt */
    public function test_genehmigter_urlaub_erstellt_absence_wenn_setting_aktiv(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-04-01',
            'end_date'   => '2026-04-03',
        ]);

        // Datum via Eloquent prüfen – SQLite speichert date als datetime-String
        $absence = Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->first();
        $this->assertNotNull($absence, 'Absence wurde nicht erstellt');
        $this->assertEquals('2026-04-01', $absence->start->format('Y-m-d'));
        $this->assertEquals('2026-04-03', $absence->end->format('Y-m-d'));
    }

    /** Genehmigter Urlaub, aber Setting deaktiviert → keine Absence */
    public function test_genehmigter_urlaub_erstellt_keine_absence_wenn_setting_inaktiv(): void
    {
        $this->settingInaktiv();
        $employee = User::factory()->create();


        Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-04-01',
            'end_date'   => '2026-04-03',
        ]);

        $count = Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->count();
        $this->assertEquals(0, $count, 'Es darf keine Absence erstellt werden wenn Setting=0');
    }

    /** Nicht genehmigter Urlaub → keine Absence, auch wenn Setting aktiv */
    public function test_nicht_genehmigter_urlaub_erstellt_keine_absence(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        Holiday::factory()->for($employee, 'employe')->create([
            'approved'   => false,
            'start_date' => '2026-04-01',
            'end_date'   => '2026-04-03',
        ]);

        $count = Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->count();
        $this->assertEquals(0, $count, 'Nicht genehmigter Urlaub darf keine Absence erzeugen');
    }

    /** Eine bereits vorhandene, unverknüpfte Urlaubs-Absence (Altbestand) wird übernommen statt gedoppelt */
    public function test_altbestand_absence_wird_verknuepft_statt_gedoppelt(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        Absence::create([
            'users_id'   => $employee->id,
            'creator_id' => $this->actor->id,
            'reason'     => 'Urlaub',
            'start'      => '2026-05-04',
            'end'        => '2026-05-05',
        ]);

        $holiday = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-05-04',
            'end_date'   => '2026-05-05',
        ]);

        $absences = Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->get();
        $this->assertCount(1, $absences, 'Altbestand darf nicht gedoppelt werden');
        $this->assertEquals($holiday->id, $absences->first()->holiday_id);
    }

    // ─── updated ─────────────────────────────────────────────────────────────

    /** Urlaub nachträglich genehmigt (update) → Absence wird erstellt */
    public function test_updated_genehmigung_erstellt_absence(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        $holiday = Holiday::factory()->for($employee, 'employe')->create([
            'approved'   => false,
            'start_date' => '2026-06-01',
            'end_date'   => '2026-06-03',
        ]);

        $holiday->update([
            'approved'    => true,
            'approved_by' => $this->actor->id,
            'approved_at' => now(),
        ]);

        $absence = Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->first();
        $this->assertNotNull($absence, 'Absence nach Genehmigung erwartet');
        $this->assertEquals('2026-06-01', $absence->start->format('Y-m-d'));
    }

    /**
     * Jede Änderung leert den Urlaubs-Cache des Mitarbeiters (User::holidays_date()).
     */
    public function test_updated_urlaub_leert_cache(): void
    {
        $this->settingInaktiv(); // Absence-Erstellung überspringen

        $employee = User::factory()->create();
        $holiday  = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-07-01',
            'end_date'   => '2026-07-02',
        ]);

        $key = 'user_holidays_' . $employee->id;
        Cache::put($key, 'daten', 300);
        $this->assertEquals('daten', Cache::get($key), 'Voraussetzung: Cache muss befüllt sein');

        $holiday->update(['approved_by' => $this->actor->id]);

        $this->assertNull(Cache::get($key), 'Urlaubs-Cache des Mitarbeiters wurde nicht geleert');
    }

    /** Ablehnung nach Genehmigung entfernt die verknüpfte Absence wieder */
    public function test_ablehnung_nach_genehmigung_entfernt_absence(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        $holiday = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-08-10',
            'end_date'   => '2026-08-12',
        ]);
        $this->assertEquals(1, Absence::where('holiday_id', $holiday->id)->count());

        $holiday->update(['approved' => false, 'rejected' => true]);

        $this->assertEquals(0, Absence::where('holiday_id', $holiday->id)->count());
    }

    /** Löscht ein anderer Benutzer den Urlaub, verschwindet die Absence trotzdem */
    public function test_loeschen_durch_andere_person_entfernt_absence(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        $holiday = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-08-17',
            'end_date'   => '2026-08-18',
        ]);

        $this->actingAs(User::factory()->create());
        $holiday->delete();

        $this->assertEquals(0, Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->count());
    }

    // ─── deleted ─────────────────────────────────────────────────────────────

    /** Genehmigter Urlaub gelöscht + Setting aktiv → Absence wird gelöscht */
    public function test_deleted_genehmigter_urlaub_loescht_absence(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        $holiday = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-03',
        ]);

        $this->assertEquals(
            1,
            Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->count(),
            'Absence muss vor dem Löschen existieren'
        );

        $holiday->delete();

        $this->assertEquals(
            0,
            Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->count(),
            'Absence muss nach dem Löschen des Urlaubs weg sein'
        );
    }

    /** Nicht genehmigter Urlaub gelöscht → vorhandene manuelle Absence bleibt */
    public function test_deleted_nicht_genehmigter_urlaub_loescht_keine_absence(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();
        $holiday  = Holiday::factory()->for($employee, 'employe')->create(['approved' => false]);

        // Manuell eine Absence anlegen
        Absence::create([
            'users_id'   => $employee->id,
            'creator_id' => $this->actor->id,
            'reason'     => 'Urlaub',
            'start'      => '2026-09-01',
            'end'        => '2026-09-01',
        ]);

        $holiday->delete();

        $this->assertEquals(
            1,
            Absence::where('users_id', $employee->id)->where('reason', 'Urlaub')->count(),
            'Manuelle Absence darf beim Löschen eines nicht genehmigten Urlaubs nicht entfernt werden'
        );
    }
}

