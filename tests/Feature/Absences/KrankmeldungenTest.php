<?php

namespace Tests\Feature\Absences;

use App\Exports\SickNotesCompleteExport;
use App\Models\Absence;
use App\Models\User;
use App\Services\KrankmeldungService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;
use Tests\TestCase;

/**
 * Krankmeldungen (/sick_notes): einheitliche Schein-Regel, Zeitraum, Statistiken,
 * Änderungen nur per POST/DELETE, Export.
 */
class KrankmeldungenTest extends TestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        // Mittwoch
        Carbon::setTestNow('2026-10-14 10:00:00');
        Notification::fake();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function krank(User $user, string $start, string $ende, array $daten = []): Absence
    {
        return Absence::factory()->create(array_merge([
            'users_id' => $user->id,
            'creator_id' => $user->id,
            'reason' => 'krank',
            'start' => $start,
            'end' => $ende,
            'showVertretungsplan' => false,
            'sick_note_required' => false,
            'sick_note_date' => null,
        ], $daten));
    }

    private function service(): KrankmeldungService
    {
        return app(KrankmeldungService::class);
    }

    public function test_schein_ab_dem_dritten_arbeitstag(): void
    {
        $ma = $this->mitarbeiter();
        // Schwelle 3 Arbeitstage: Mo–Di braucht keinen Schein, Mo–Mi schon (fällig am Mittwoch)
        $zwei = $this->krank($ma, '2026-10-05', '2026-10-06');
        $drei = $this->krank($ma, '2026-09-21', '2026-09-23');
        // Fr–Mo sind nur 2 Arbeitstage
        $wochenende = $this->krank($ma, '2026-09-25', '2026-09-28');

        $this->assertSame('nicht_noetig', $this->service()->status($zwei));
        $this->assertTrue($this->service()->istKurzerkrankung($zwei));
        $this->assertSame('nicht_noetig', $this->service()->status($wochenende->fresh()));
        $this->assertFalse($wochenende->fresh()->sick_note_required);

        $this->assertSame('fehlt', $this->service()->status($drei->fresh()));
        $this->assertTrue($drei->fresh()->sick_note_required, 'Model setzt die Pflicht automatisch');
        $this->assertSame('2026-09-23', $this->service()->faelligAb($drei)->toDateString());
    }

    public function test_laufende_krankheit_ist_noch_nicht_faellig(): void
    {
        $ma = $this->mitarbeiter();
        $laufend = $this->krank($ma, '2026-10-13', '2026-10-20');

        $this->assertSame('ausstehend', $this->service()->status($laufend->fresh()));
        // Di 13.10. → 3. Arbeitstag ist Do 15.10.
        $this->assertSame('2026-10-15', $this->service()->faelligAb($laufend)->toDateString());
    }

    public function test_kind_krank_ist_keine_kurzerkrankung(): void
    {
        $ma = $this->mitarbeiter();
        $kind = $this->krank($ma, '2026-10-05', '2026-10-05', ['reason' => 'Kind krank']);

        $this->assertSame('nicht_noetig', $this->service()->status($kind));
        $this->assertFalse($this->service()->istKurzerkrankung($kind));
    }

    public function test_seite_mit_kennzahlen_und_statistik(): void
    {
        $hr = $this->mitarbeiter(['manage sick_notes']);
        $ma = $this->mitarbeiter();
        $ma->update(['name' => 'Karla Krank']);
        $this->krank($ma, '2026-10-05', '2026-10-06');            // Mo–Di, 2 Arbeitstage, kurz
        $this->krank($ma, '2026-09-21', '2026-09-24');            // Mo–Do, 4 Arbeitstage, Schein fehlt

        $this->actingAs($hr)->get('/sick_notes')
            ->assertOk()
            ->assertSee('Karla Krank')
            ->assertSee('Krankheitstage je Monat')
            ->assertSee('Beginn nach Wochentag')
            ->assertSee('Krankenscheine fehlen')
            ->assertSee('data-tour-config', false)
            ->assertViewHas('kennzahlen', fn (array $k) => $k['faelle'] === 2 && $k['tage'] === 6 && $k['fehlt'] === 1 && $k['kurz'] === 1)
            ->assertViewHas('wochentage', fn (array $w) => $w['Mo'] === 2);

        $this->get('/sick_notes?status=fehlt')
            ->assertOk()
            ->assertViewHas('meldungen', fn ($m) => $m->total() === 1 && $m->first()->start->toDateString() === '2026-09-21');
    }

    public function test_zeitraum_zaehlt_tage_anteilig(): void
    {
        $hr = $this->mitarbeiter(['manage sick_notes']);
        $ma = $this->mitarbeiter();
        // Do 30.07.2026 – Di 04.08.2026: 2 Arbeitstage im Juli, 2 im August
        $this->krank($ma, '2026-07-30', '2026-08-04');

        $this->actingAs($hr)->get('/sick_notes?zeitraum=schuljahr')
            ->assertOk()
            ->assertViewHas('kennzahlen', fn (array $k) => $k['faelle'] === 1 && $k['tage'] === 2);
    }

    public function test_ungueltige_parameter_werden_abgelehnt(): void
    {
        $hr = $this->mitarbeiter(['manage sick_notes']);

        $this->actingAs($hr)->get('/sick_notes?richtung=drop')->assertSessionHasErrors('richtung');
        $this->get('/sick_notes?sort=password')->assertSessionHasErrors('sort');
    }

    public function test_ohne_recht_kein_zugriff(): void
    {
        $this->actingAs($this->mitarbeiter(['view absences']))->get('/sick_notes')->assertForbidden();
    }

    public function test_schein_erfassen_und_entfernen_nur_per_post_delete(): void
    {
        $hr = $this->mitarbeiter(['manage sick_notes']);
        $absence = $this->krank($this->mitarbeiter(), '2026-09-21', '2026-09-24');
        $this->actingAs($hr);

        $this->get("/sick_notes/{$absence->id}/set_note_date")->assertNotFound();

        $this->post("/sick_notes/{$absence->id}/schein", ['datum' => '2026-09-25'])->assertRedirect();
        $this->assertSame('2026-09-25', $absence->fresh()->sick_note_date->toDateString());

        $this->post("/sick_notes/{$absence->id}/schein", ['datum' => '2026-12-01'])->assertSessionHasErrors('datum');

        $this->delete("/sick_notes/{$absence->id}/schein")->assertRedirect();
        $this->assertNull($absence->fresh()->sick_note_date);
    }

    public function test_schein_fuer_kurze_krankheit_verlangen(): void
    {
        $hr = $this->mitarbeiter(['manage sick_notes']);
        $absence = $this->krank($this->mitarbeiter(), '2026-10-05', '2026-10-06');
        $this->actingAs($hr);

        $this->post("/sick_notes/{$absence->id}/pflicht", ['pflicht' => 1])->assertRedirect();
        $absence->refresh();
        $this->assertTrue($absence->sick_note_required);
        $this->assertSame('fehlt', $this->service()->status($absence));
        $this->assertSame('2026-10-05', $this->service()->faelligAb($absence)->toDateString());
    }

    public function test_andere_abwesenheiten_nicht_bearbeitbar(): void
    {
        $hr = $this->mitarbeiter(['manage sick_notes']);
        $fortbildung = $this->krank($this->mitarbeiter(), '2026-10-05', '2026-10-06', ['reason' => 'Fortbildung']);

        $this->actingAs($hr)->post("/sick_notes/{$fortbildung->id}/schein")->assertNotFound();
    }

    public function test_bem_hinweis_ab_sechs_wochen(): void
    {
        $hr = $this->mitarbeiter(['manage sick_notes']);
        $ma = $this->mitarbeiter();
        // 01.06.–17.07.2026: deutlich über 30 Arbeitstage
        $this->krank($ma, '2026-06-01', '2026-07-17');
        $kurz = $this->mitarbeiter();
        $this->krank($kurz, '2026-10-05', '2026-10-06');

        $this->actingAs($hr)->get('/sick_notes')
            ->assertOk()
            ->assertSee('BEM prüfen')
            ->assertViewHas('personen', fn ($p) => $p->firstWhere('user_id', $ma->id)['bem'] >= 30
                && $p->firstWhere('user_id', $kurz->id)['bem'] === null);
    }

    public function test_export_mit_statistik(): void
    {
        Excel::fake();
        $hr = $this->mitarbeiter(['manage sick_notes']);
        $ma = $this->mitarbeiter();
        $this->krank($ma, '2026-10-05', '2026-10-07');

        $this->actingAs($hr)->get('/sick_notes/export?zeitraum=jahr')->assertOk();

        Excel::assertDownloaded('Krankmeldungen_2026-01-01_bis_2026-12-31.xlsx', function (SickNotesCompleteExport $export) {
            $blaetter = $export->sheets();

            return count($blaetter) === 3
                && $blaetter[0]->collection()->count() === 1
                && $blaetter[2]->array()[4] === ['Krankheitstage (Arbeitstage im Zeitraum)', 3];
        });

        $ma->update(['name' => 'Ana/Lyse [Test]']);
        $this->get("/sick_notes/export/user/{$ma->id}")->assertOk();
        $this->assertSame('Ana Lyse Test', KrankmeldungService::sichererName('Ana/Lyse [Test]'));
    }
}
