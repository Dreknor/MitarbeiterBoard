<?php

namespace Tests\Unit\Services;

use App\Models\Group;
use App\Models\User;
use App\Models\personal\HortFaktor;
use App\Models\personal\HortFaktorWert;
use App\Models\personal\HortMonatZusatz;
use App\Models\personal\HortPlanung;
use App\Models\personal\HortPlanungMonat;
use App\Models\personal\HortPlanungPerson;
use App\Models\personal\HortZusatzstundenTyp;
use App\Services\HortPlanungService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Unit-Tests für HortPlanungService (Konzept §12.1).
 *
 * Alle Tests arbeiten mit SQLite in-memory (RefreshDatabase).
 * Es werden keine HTTP-Requests gestellt.
 */
class HortPlanungServiceTest extends TestCase
{
    private HortPlanungService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HortPlanungService();
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────

    /**
     * Erstellt eine vollständige Planung mit Standard-Faktoren und einem Monat.
     */
    private function erstellePlanungMitMonat(
        Carbon $monat = null,
        int $kinderanzahl = 100,
        float $vollzeit = 40
    ): array {
        $monat ??= Carbon::create(2024, 1, 1);
        $user  = User::factory()->create();
        $dept  = Group::factory()->asDepartment()->create();

        $planung = HortPlanung::create([
            'name'          => 'Testplanung',
            'department_id' => $dept->id,
            'start_monat'   => $monat->copy()->startOfMonth(),
            'end_monat'     => $monat->copy()->startOfMonth(),
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => $user->id,
        ]);

        $this->service->erstelleStandardFaktoren($planung);
        $this->service->erstelleStandardZusatztypen($planung);

        $hortMonat = HortPlanungMonat::create([
            'hort_planung_id' => $planung->id,
            'monat'           => $monat->format('Y-m-01'),
            'kinderanzahl'    => $kinderanzahl,
            'vollzeitstunden' => $vollzeit,
        ]);

        $planung->load(['faktoren.werte', 'zusatzstundenTypen', 'monate.personen', 'monate.monatZusatzstunden.typ']);

        return compact('planung', 'hortMonat', 'user', 'dept');
    }

    /**
     * Fügt eine Person mit Stunden einem Monat hinzu.
     */
    private function fuegePersonHinzu(HortPlanungMonat $monat, ?float $gesamt = 25, ?float $stadt = 20): HortPlanungPerson
    {
        $user = User::factory()->create();
        return HortPlanungPerson::create([
            'hort_planung_monat_id' => $monat->id,
            'user_id'               => $user->id,
            'stunden_gesamt'        => $gesamt,
            'stunden_stadt'         => $stadt,
        ]);
    }

    // ── §12.1 Test: berechneMonat Standardfall ────────────────────────

    public function test_berechneMonat_standardfall(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat(
            Carbon::create(2024, 1, 1),
            100,
            40
        );

        // Monat frisch laden
        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        // Betreuungsschlüssel: 100 / 22.2222 ≈ 4.5000
        $this->assertEqualsWithDelta(4.5, $ergebnis['betreuungsschluessel'], 0.01, 'Betreuungsschlüssel');
        $this->assertArrayHasKey('faktoren', $ergebnis);
        $this->assertArrayHasKey('kinderschluessel', $ergebnis['faktoren']);
        $this->assertArrayHasKey('budget_rest_sp1', $ergebnis);
        $this->assertArrayHasKey('summe_gesetz_vz', $ergebnis);
    }

    // ── §12.1 Test: dynamische Faktoren ───────────────────────────────

    public function test_berechneMonat_dynamische_faktoren(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat();
        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        // Alle 4 aktiven Standard-Faktoren müssen vorhanden sein (mentor ist inaktiv)
        $this->assertArrayHasKey('kinderschluessel', $ergebnis['faktoren']);
        $this->assertArrayHasKey('leitung',          $ergebnis['faktoren']);
        $this->assertArrayHasKey('vorbereitung',     $ergebnis['faktoren']);
        $this->assertArrayHasKey('anpassung',        $ergebnis['faktoren']);
        $this->assertArrayNotHasKey('mentor',        $ergebnis['faktoren'], 'Mentor ist inaktiv und darf nicht erscheinen');
    }

    // ── §12.1 Test: faktor_auf_summe ──────────────────────────────────

    public function test_berechneMonat_faktor_auf_summe(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat(
            Carbon::create(2024, 1, 1), 100, 40
        );
        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        // Anpassung = (BS + Leitung + Vorbereitung) × 0.04
        $bs    = $ergebnis['faktoren']['kinderschluessel']['vz'];
        $leit  = $ergebnis['faktoren']['leitung']['vz'];
        $vorb  = $ergebnis['faktoren']['vorbereitung']['vz'];
        $anp   = $ergebnis['faktoren']['anpassung']['vz'];

        $erwartet = round(($bs + $leit + $vorb) * 0.04, 5);
        $this->assertEqualsWithDelta($erwartet, $anp, 0.0001, 'Anpassung = (BS+Leitung+Vorb) × 0.04');
    }

    // ── §12.1 Test: zeitlich variabler Faktor-Wert ───────────────────

    public function test_berechneMonat_faktor_zeitlich_variabel(): void
    {
        // Planung ab Januar 2024: initialer Faktor gueltig_ab = '2024-01-01'
        ['planung' => $planung, 'hortMonat' => $monatJan] = $this->erstellePlanungMitMonat(
            Carbon::create(2024, 1, 1), 100, 40
        );

        // Kinderschlüssel-Faktor finden und neuen Wert ab April 2024 setzen
        $ksf = $planung->faktoren->firstWhere('kuerzel', 'kinderschluessel');
        HortFaktorWert::create([
            'hort_faktor_id' => $ksf->id,
            'wert'           => 20.0, // Neuer Wert ab April 2024
            'gueltig_ab'     => '2024-04-01',
            'notiz'          => 'Test-Änderung',
            'created_by'     => $planung->created_by,
        ]);

        // Monat Juni 2024 – hier soll der neue Faktor (20.0) gelten
        $monatJun = HortPlanungMonat::create([
            'hort_planung_id' => $planung->id,
            'monat'           => '2024-06-01',
            'kinderanzahl'    => 100,
            'vollzeitstunden' => 40,
        ]);

        $monatJan->load(['personen', 'monatZusatzstunden.typ']);
        $monatJun->load(['personen', 'monatZusatzstunden.typ']);

        $ergJan = $this->service->berechneMonat($monatJan);
        $ergJun = $this->service->berechneMonat($monatJun);

        // Jan 2024: Schlüssel = 22.2222 → BS ≈ 4.5
        $this->assertEqualsWithDelta(4.5, $ergJan['betreuungsschluessel'], 0.1, 'Jan: alter Schlüssel');

        // Jun 2024: Schlüssel = 20.0 → BS = 5.0
        $this->assertEqualsWithDelta(5.0, $ergJun['betreuungsschluessel'], 0.1, 'Jun: neuer Schlüssel');
    }

    // ── §12.1 Test: deaktivierter Faktor ─────────────────────────────

    public function test_berechneMonat_deaktivierter_faktor(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat();

        // Leitung deaktivieren
        $leitFaktor = $planung->faktoren->firstWhere('kuerzel', 'leitung');
        $leitFaktor->update(['aktiv' => false]);

        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        $this->assertArrayNotHasKey('leitung', $ergebnis['faktoren'], 'Deaktivierter Faktor darf nicht berechnet werden');
    }

    // ── §12.1 Test: Budget-Rest ───────────────────────────────────────

    public function test_berechneMonat_budget_rest(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat(
            Carbon::create(2024, 1, 1), 100, 40
        );

        // 2 Personen mit je 25h SP1
        $this->fuegePersonHinzu($monat, 25, 20);
        $this->fuegePersonHinzu($monat, 30, 25);

        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        // Budget-Rest = (gesetzl. Stunden + Zusatz) − SP1
        $erwartetBudgetRest = $ergebnis['budget_gesamt'] - $ergebnis['summe_sp1'];
        $this->assertEqualsWithDelta(
            $erwartetBudgetRest,
            $ergebnis['budget_rest_sp1'],
            0.01,
            'Budget-Rest = Budget-Gesamt − Summe SP1'
        );
    }

    // ── §12.1 Test: Differenz VZÄ SP2 ────────────────────────────────

    public function test_berechneMonat_differenz_vz_sp2(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat();

        $this->fuegePersonHinzu($monat, 40, 34);
        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        $erwartet = round($ergebnis['summe_vz_sp2'] - $ergebnis['summe_gesetz_vz'], 4);
        $this->assertEqualsWithDelta(
            $erwartet,
            $ergebnis['differenz_vz_sp2'],
            0.0001,
            'Differenz VZÄ SP2 = VZÄ-SP2 − gesetzl. VZÄ'
        );
    }

    // ── §12.1 Test: Division by Zero ─────────────────────────────────

    public function test_berechneMonat_division_by_zero(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat(
            Carbon::create(2024, 1, 1), 0, 0 // Kinderanzahl und Vollzeitstunden = 0
        );

        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        // Kein Fehler darf auftreten
        $ergebnis = $this->service->berechneMonat($monat);

        $this->assertIsArray($ergebnis);
        $this->assertEquals(0, $ergebnis['summe_sp1']);
        $this->assertEquals(0, $ergebnis['betreuungsschluessel']);
    }

    // ── §12.1 Test: Leerer Monat ──────────────────────────────────────

    public function test_berechneMonat_ohne_personen(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat();
        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        $this->assertEquals(0.0, $ergebnis['summe_sp1']);
        $this->assertEquals(0.0, $ergebnis['summe_sp2']);
    }

    // ── §12.1 Test: Dynamische Zusatzstunden ─────────────────────────

    public function test_berechneMonat_dynamische_zusatzstunden(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat();

        $zusatzTypen = $planung->zusatzstundenTypen->where('aktiv', true);
        $this->assertGreaterThanOrEqual(2, $zusatzTypen->count(), 'Mindestens 2 Standard-Zusatztypen');

        // Stunden für jeden Typ setzen
        foreach ($zusatzTypen as $typ) {
            HortMonatZusatz::create([
                'hort_planung_monat_id'     => $monat->id,
                'hort_zusatzstunden_typ_id' => $typ->id,
                'stunden'                   => 8.0,
            ]);
        }

        $monat->load(['personen', 'monatZusatzstunden.typ']);
        $planung->load('faktoren.werte');

        $ergebnis = $this->service->berechneMonat($monat);

        $this->assertEquals(
            round($zusatzTypen->count() * 8.0, 2),
            $ergebnis['summe_zusatzstunden'],
            'Summe Zusatzstunden = Anzahl Typen × 8h'
        );
    }

    // ── §12.1 Test: Grundarbeitszeit-Aufschlüsselung ─────────────────

    public function test_grundarbeitszeit_aufschluesselung(): void
    {
        ['planung' => $planung, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat();
        $person = $this->fuegePersonHinzu($monat, 40, 34);

        $person->load('monat.planung.faktoren.werte');

        $aufschluesselung = $this->service->berechneGrundarbeitszeit($person);

        $this->assertArrayHasKey('vz_anteil',     $aufschluesselung);
        $this->assertArrayHasKey('wochenstunden', $aufschluesselung);
        $this->assertArrayHasKey('erzieher_vz',   $aufschluesselung);

        // VZ-Anteil = Wochenstunden / Vollzeitstunden
        $this->assertEqualsWithDelta(1.0, $aufschluesselung['vz_anteil'], 0.01, 'Vollzeitkraft hat VZ-Anteil 1.0');
    }

    // ── §12.1 Test: erstelleStandardFaktoren ─────────────────────────

    public function test_erstelleStandardFaktoren(): void
    {
        $user  = User::factory()->create();
        $dept  = Group::factory()->asDepartment()->create();
        $this->actingAs($user);

        $planung = HortPlanung::create([
            'name'          => 'Test',
            'department_id' => $dept->id,
            'start_monat'   => '2024-01-01',
            'end_monat'     => '2024-12-01',
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => $user->id,
        ]);

        $this->service->erstelleStandardFaktoren($planung);

        $faktoren = $planung->faktoren()->with('werte')->get();

        $this->assertEquals(5, $faktoren->count(), '5 Standard-Faktoren erwartet');
        $this->assertTrue($faktoren->pluck('kuerzel')->contains('kinderschluessel'));
        $this->assertTrue($faktoren->pluck('kuerzel')->contains('leitung'));
        $this->assertTrue($faktoren->pluck('kuerzel')->contains('vorbereitung'));
        $this->assertTrue($faktoren->pluck('kuerzel')->contains('anpassung'));
        $this->assertTrue($faktoren->pluck('kuerzel')->contains('mentor'));

        // Jeder Faktor hat genau einen Wert
        foreach ($faktoren as $faktor) {
            $this->assertEquals(1, $faktor->werte->count(), "Faktor '{$faktor->kuerzel}' soll 1 initialen Wert haben");
        }

        // Kinderschlüssel ist aktiv, Mentor inaktiv
        $this->assertTrue($faktoren->firstWhere('kuerzel', 'kinderschluessel')->aktiv);
        $this->assertFalse($faktoren->firstWhere('kuerzel', 'mentor')->aktiv);
    }

    // ── §12.1 Test: erstelleStandardZusatztypen ──────────────────────

    public function test_erstelleStandardZusatztypen(): void
    {
        $user  = User::factory()->create();
        $dept  = Group::factory()->asDepartment()->create();
        $this->actingAs($user);

        $planung = HortPlanung::create([
            'name'          => 'Test',
            'department_id' => $dept->id,
            'start_monat'   => '2024-01-01',
            'end_monat'     => '2024-12-01',
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => $user->id,
        ]);

        $this->service->erstelleStandardZusatztypen($planung);

        $typen = $planung->zusatzstundenTypen()->get();

        $this->assertEquals(2, $typen->count(), '2 Standard-Zusatztypen erwartet');
        $this->assertTrue($typen->pluck('kuerzel')->contains('zusatz_weg'));
        $this->assertTrue($typen->pluck('kuerzel')->contains('sonstige'));
    }

    // ── §12.1 Test: wertFuerMonat zeitlich ───────────────────────────

    public function test_wertFuerMonat_zeitlich(): void
    {
        ['planung' => $planung] = $this->erstellePlanungMitMonat();

        $ksf = $planung->faktoren->firstWhere('kuerzel', 'kinderschluessel');

        // Zweiten Wert ab April 2024 hinzufügen
        HortFaktorWert::create([
            'hort_faktor_id' => $ksf->id,
            'wert'           => 20.0,
            'gueltig_ab'     => '2024-04-01',
            'notiz'          => 'Neue Verordnung',
            'created_by'     => $planung->created_by,
        ]);

        $ksf->load('werte');

        $wertJan = $ksf->wertFuerMonat(Carbon::create(2024, 1, 1));
        $wertApr = $ksf->wertFuerMonat(Carbon::create(2024, 4, 1));
        $wertDez = $ksf->wertFuerMonat(Carbon::create(2024, 12, 1));

        $this->assertEqualsWithDelta(22.222222, $wertJan, 0.001, 'Jan: initialer Wert');
        $this->assertEqualsWithDelta(20.0, $wertApr, 0.001, 'Apr: neuer Wert');
        $this->assertEqualsWithDelta(20.0, $wertDez, 0.001, 'Dez: neuer Wert bleibt gültig');
    }

    // ── §12.1 Test: bulkUpdatePerson ─────────────────────────────────

    public function test_bulkUpdatePerson(): void
    {
        $user  = User::factory()->create();
        $dept  = Group::factory()->asDepartment()->create();
        $this->actingAs($user);

        $planung = HortPlanung::create([
            'name'          => 'Test',
            'department_id' => $dept->id,
            'start_monat'   => '2024-01-01',
            'end_monat'     => '2024-06-01',
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => $user->id,
        ]);

        $this->service->erstelleMonate($planung);
        $planung->load('monate');

        $person = User::factory()->create();
        $abMonat = Carbon::create(2024, 3, 1);

        $count = $this->service->bulkUpdatePerson($planung, $person->id, $abMonat, 30.0, 25.0);

        // Ab März 2024 bis Juni 2024 = 4 Monate
        $this->assertEquals(4, $count);

        // Januar und Februar sollten keine Einträge haben
        $janMonat = $planung->monate->first(fn($m) => $m->monat->format('Y-m-d') === '2024-01-01');
        $this->assertNotNull($janMonat, 'Januar-Monat muss in der Planung vorhanden sein');
        $this->assertNull(
            HortPlanungPerson::where('hort_planung_monat_id', $janMonat->id)
                ->where('user_id', $person->id)->first()
        );

        // März sollte 30h haben
        $maerMonat = $planung->monate->first(fn($m) => $m->monat->format('Y-m-d') === '2024-03-01');
        $this->assertNotNull($maerMonat, 'März-Monat muss in der Planung vorhanden sein');
        $maerPerson = HortPlanungPerson::where('hort_planung_monat_id', $maerMonat->id)
            ->where('user_id', $person->id)->first();
        $this->assertNotNull($maerPerson);
        $this->assertEquals(30.0, $maerPerson->stunden_gesamt);
    }

    // ── §12.1 Test: dupliziere ────────────────────────────────────────

    public function test_dupliziere(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ['planung' => $original, 'hortMonat' => $monat] = $this->erstellePlanungMitMonat();
        $this->fuegePersonHinzu($monat, 25, 20);

        $kopie = $this->service->dupliziere($original, 'Kopie Test', 'Beschreibung');

        $this->assertInstanceOf(HortPlanung::class, $kopie);
        $this->assertEquals('Kopie Test', $kopie->name);
        $this->assertEquals($original->id, $kopie->kopiert_von_id);
        $this->assertFalse($kopie->aktiv, 'Kopie ist nicht aktiv');

        // Faktoren wurden kopiert
        $this->assertEquals(
            $original->faktoren->count(),
            $kopie->faktoren->count(),
            'Gleiche Anzahl Faktoren'
        );

        // Monate wurden kopiert
        $this->assertEquals(
            $original->monate->count(),
            $kopie->monate->count(),
            'Gleiche Anzahl Monate'
        );
    }

    // ── §12.1 Test: vergleichePlanungen ──────────────────────────────

    public function test_vergleichePlanungen(): void
    {
        $user = User::factory()->create();
        $dept = Group::factory()->asDepartment()->create();
        $this->actingAs($user);

        $start = Carbon::create(2024, 1, 1);

        [$planungA, $planungB] = collect([1, 2])->map(fn($i) => HortPlanung::create([
            'name'          => "Plan $i",
            'department_id' => $dept->id,
            'start_monat'   => $start,
            'end_monat'     => $start->copy()->addMonths(2),
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => $user->id,
        ]));

        foreach ([$planungA, $planungB] as $pl) {
            $this->service->erstelleMonate($pl);
            $this->service->erstelleStandardFaktoren($pl);
            $this->service->erstelleStandardZusatztypen($pl);
        }

        $vergleich = $this->service->vergleichePlanungen($planungA, $planungB);

        $this->assertGreaterThan(0, $vergleich->count());

        $erstesErgebnis = $vergleich->first();
        $this->assertArrayHasKey('monat',     $erstesErgebnis);
        $this->assertArrayHasKey('planung_a', $erstesErgebnis);
        $this->assertArrayHasKey('planung_b', $erstesErgebnis);
        $this->assertArrayHasKey('diff_sp1',  $erstesErgebnis);
    }

    // ── §12.1 Test: trendDaten Format ────────────────────────────────

    public function test_trendDaten_format(): void
    {
        $user = User::factory()->create();
        $dept = Group::factory()->asDepartment()->create();
        $this->actingAs($user);

        $planung = HortPlanung::create([
            'name'          => 'Test',
            'department_id' => $dept->id,
            'start_monat'   => '2024-01-01',
            'end_monat'     => '2024-03-01',
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => $user->id,
        ]);

        $this->service->erstelleMonate($planung);
        $this->service->erstelleStandardFaktoren($planung);
        $this->service->erstelleStandardZusatztypen($planung);

        $planung->load(['faktoren.werte', 'monate.personen', 'monate.monatZusatzstunden.typ']);

        $trend = $this->service->trendDaten($planung);

        $this->assertArrayHasKey('labels',   $trend);
        $this->assertArrayHasKey('datasets', $trend);
        $this->assertCount(3, $trend['labels'], '3 Monate = 3 Labels');
        $this->assertArrayHasKey('vz_sp1',          $trend['datasets']);
        $this->assertArrayHasKey('vz_gesetzlich',   $trend['datasets']);
        $this->assertArrayHasKey('budget_rest_sp1', $trend['datasets']);
    }

    // ── §12.1 Test: syncIstStunden – nur vergangene Monate ───────────

    public function test_syncIstStunden_nur_vergangene_monate(): void
    {
        $user = User::factory()->create();
        $dept = Group::factory()->asDepartment()->create();
        $this->actingAs($user);

        $planung = HortPlanung::create([
            'name'          => 'Test',
            'department_id' => $dept->id,
            'start_monat'   => now()->addMonths(1)->startOfMonth(),
            'end_monat'     => now()->addMonths(3)->startOfMonth(),
            'typ'           => 'planung',
            'aktiv'         => false,
            'created_by'    => $user->id,
        ]);

        $this->service->erstelleMonate($planung);
        $planung->load('monate.personen');

        // Kein Monat in der Vergangenheit → 0 synchronisiert
        $count = $this->service->syncIstStunden($planung);

        $this->assertEquals(0, $count, 'Keine Sync für zukünftige Monate');
    }

    // ── §12.1 Test: erstelleSnapshot ─────────────────────────────────

    public function test_erstelleSnapshot(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ['planung' => $planung] = $this->erstellePlanungMitMonat();
        $planung->load(['faktoren.werte', 'monate.personen', 'monate.monatZusatzstunden.typ']);

        $snapshot = $this->service->erstelleSnapshot($planung, 'Test-Snapshot');

        $this->assertNotNull($snapshot->id);
        $this->assertEquals('Test-Snapshot', $snapshot->name);
        $this->assertIsArray($snapshot->daten);
    }
}

