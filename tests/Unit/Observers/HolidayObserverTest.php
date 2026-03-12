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

    /** firstOrCreate verhindert Duplikat-Absences */
    public function test_doppelter_urlaub_erzeugt_keine_doppelte_absence(): void
    {
        $this->settingAktiv();
        $employee = User::factory()->create();

        $data = ['start_date' => '2026-05-01', 'end_date' => '2026-05-02'];
        Holiday::factory()->for($employee, 'employe')->approved()->create($data);
        Holiday::factory()->for($employee, 'employe')->approved()->create($data);

        $count = Absence::where('users_id', $employee->id)
            ->where('reason', 'Urlaub')
            ->count();
        $this->assertEquals(1, $count, 'Doppelter Urlaub darf nur eine Absence erzeugen');
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
     * Update leert den Tages-Cache.
     * Hinweis: touch() feuert kein updated-Event wenn updated_at unverändert bleibt.
     * Deshalb wird approved_by explizit geändert, um den Observer sicher auszulösen.
     */
    public function test_updated_urlaub_leert_cache(): void
    {
        $this->settingInaktiv(); // Absence-Erstellung überspringen
        $this->actingAs($this->actor);

        $employee = User::factory()->create();
        $holiday  = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-07-01',
            'end_date'   => '2026-07-02',
        ]);

        $authId = auth()->id();
        $key1 = 'holiday_' . $authId . '_2026-07-01';
        $key2 = 'holiday_' . $authId . '_2026-07-02';

        Cache::put($key1, 'daten', 300);
        Cache::put($key2, 'daten', 300);
        $this->assertEquals('daten', Cache::get($key1), 'Voraussetzung: Cache muss befüllt sein');

        // update() statt touch(), damit das Model dirty ist und der updated-Event ausgelöst wird
        $holiday->update(['approved_by' => $this->actor->id]);

        $this->assertNull(Cache::get($key1), 'Cache-Schlüssel für Tag 1 wurde nicht geleert');
        $this->assertNull(Cache::get($key2), 'Cache-Schlüssel für Tag 2 wurde nicht geleert');
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

