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

        // Observer ruft auth()->id() auf → immer eingeloggt sein
        $this->actor = User::factory()->create();
        $this->actingAs($this->actor);

        // Settings- und allgemeinen Cache leeren
        Cache::flush();
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function settingAktiv(): void
    {
        Setting::factory()->forKey('absence_auto_create', '1', 'holidays')->create();
        Cache::flush(); // Setting-Cache invalidieren
    }

    private function settingInaktiv(): void
    {
        Setting::factory()->forKey('absence_auto_create', '0', 'holidays')->create();
        Cache::flush();
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

        $this->assertDatabaseHas('absences', [
            'users_id' => $employee->id,
            'reason'   => 'Urlaub',
            'start'    => '2026-04-01',
            'end'      => '2026-04-03',
        ]);
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

        $this->assertDatabaseMissing('absences', [
            'users_id' => $employee->id,
            'reason'   => 'Urlaub',
        ]);
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

        $this->assertDatabaseMissing('absences', [
            'users_id' => $employee->id,
            'reason'   => 'Urlaub',
        ]);
    }

    /** firstOrCreate verhindert Duplikat-Absences */
    public function test_doppelter_urlaub_erzeugt_keine_doppelte_absence(): void
    {
        $this->settingAktiv();

        $employee = User::factory()->create();
        $data = [
            'start_date' => '2026-05-01',
            'end_date'   => '2026-05-02',
        ];

        Holiday::factory()->for($employee, 'employe')->approved()->create($data);
        Holiday::factory()->for($employee, 'employe')->approved()->create($data);

        $this->assertEquals(1, Absence::where([
            'users_id' => $employee->id,
            'reason'   => 'Urlaub',
            'start'    => '2026-05-01',
        ])->count());
    }

    // ─── updated ─────────────────────────────────────────────────────────────

    /** Urlaub nachträglich genehmigt (update) → Absence wird erstellt */
    public function test_updated_genehmigung_erstellt_absence(): void
    {
        $this->settingAktiv();

        $employee = User::factory()->create();
        $holiday  = Holiday::factory()->for($employee, 'employe')->create([
            'approved'   => false,
            'start_date' => '2026-06-01',
            'end_date'   => '2026-06-03',
        ]);

        $holiday->update([
            'approved'    => true,
            'approved_by' => $this->actor->id,
            'approved_at' => now(),
        ]);

        $this->assertDatabaseHas('absences', [
            'users_id' => $employee->id,
            'reason'   => 'Urlaub',
            'start'    => '2026-06-01',
        ]);
    }

    /** Update eines Urlaubs leert den Tages-Cache (Setting deaktiviert für Isolierung) */
    public function test_updated_urlaub_leert_cache(): void
    {
        // Setting deaktivieren um die Absence-Erstellung zu überspringen
        $this->settingInaktiv();
        $this->actingAs($this->actor); // sicherstellen dass auth gesetzt ist

        $employee = User::factory()->create();
        $holiday  = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-07-01',
            'end_date'   => '2026-07-02',
        ]);

        // Cache befüllen – Key-Aufbau identisch zum Observer
        $authId = auth()->id();
        $key1 = 'holiday_' . $authId . '_2026-07-01';
        $key2 = 'holiday_' . $authId . '_2026-07-02';
        Cache::put($key1, 'daten', 300);
        Cache::put($key2, 'daten', 300);

        $this->assertEquals('daten', Cache::get($key1), 'Voraussetzung: Cache befüllt');

        $holiday->touch(); // löst updated aus

        $this->assertNull(Cache::get($key1), 'key1 wurde nicht geleert');
        $this->assertNull(Cache::get($key2), 'key2 wurde nicht geleert');
    }

    // ─── deleted ─────────────────────────────────────────────────────────────

    /** Genehmigter Urlaub gelöscht + Setting aktiv → Absence wird gelöscht */
    public function test_deleted_genehmigter_urlaub_loescht_absence(): void
    {
        $this->settingAktiv();

        $employee = User::factory()->create();
        $holiday  = Holiday::factory()->for($employee, 'employe')->approved()->create([
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-03',
        ]);

        $this->assertDatabaseHas('absences', ['users_id' => $employee->id, 'reason' => 'Urlaub']);

        $holiday->delete();

        $this->assertDatabaseMissing('absences', [
            'users_id'   => $employee->id,
            'reason'     => 'Urlaub',
            'start'      => '2026-08-01',
            'deleted_at' => null,
        ]);
    }

    /** Nicht genehmigter Urlaub gelöscht → vorhandene Absence bleibt bestehen */
    public function test_deleted_nicht_genehmigter_urlaub_loescht_keine_absence(): void
    {
        $this->settingAktiv();

        $employee = User::factory()->create();
        $holiday  = Holiday::factory()->for($employee, 'employe')->create(['approved' => false]);

        Absence::create([
            'users_id'   => $employee->id,
            'creator_id' => $this->actor->id,
            'reason'     => 'Urlaub',
            'start'      => '2026-09-01',
            'end'        => '2026-09-01',
        ]);

        $holiday->delete();

        $this->assertDatabaseHas('absences', [
            'users_id' => $employee->id,
            'reason'   => 'Urlaub',
        ]);
    }
}

