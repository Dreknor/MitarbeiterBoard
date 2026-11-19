<?php

namespace Tests\Feature\Absences;

use App\Models\Absence;
use App\Models\AbsenceSerie;
use App\Models\User;
use App\Models\VertretungsplanAbsence;
use App\Notifications\AbwesenheitGemeldet;
use App\Notifications\AbwesenheitsserieGemeldet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;
use Tests\TestCase;

/**
 * Wiederkehrende Abwesenheiten: Termine als einzelne Abwesenheiten (absences.serie_id),
 * Rhythmen, übersprungene Tage, Regeln und Löschen der zukünftigen Termine.
 */
class WiederkehrendeAbwesenheitenTest extends TestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00'); // Mittwoch
        Notification::fake();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function serie(User $actor, array $daten = [])
    {
        return $this->actingAs($actor->fresh())->post('/absences', array_merge([
            'users_id' => $actor->id,
            'reason' => 'Fortbildung',
            'start' => '2026-10-14',
            'end' => '2026-10-31',
            'showVertretungsplan' => 1,
            'wiederkehrend' => 1,
            'rhythmus' => 'woechentlich',
            'wochentage' => [2, 4],
            'intervall' => 1,
        ], $daten));
    }

    /** @return array<int, string> */
    private function tage(User $user): array
    {
        return Absence::where('users_id', $user->id)->orderBy('start')->get()
            ->map(fn (Absence $a) => $a->start->toDateString())->all();
    }

    public function test_woechentliche_serie_legt_eintaegige_termine_an(): void
    {
        $ich = $this->mitarbeiter(['view absences']);

        $this->serie($ich)->assertRedirect()->assertSessionHas('type', 'success');

        $this->assertSame(['2026-10-15', '2026-10-20', '2026-10-22', '2026-10-27', '2026-10-29'], $this->tage($ich));

        $serie = AbsenceSerie::sole();
        $this->assertSame('jeden Dienstag und Donnerstag', $serie->rhythmusText());
        $this->assertSame(5, Absence::where('serie_id', $serie->id)->whereColumn('start', 'end')->count());
        $this->assertSame(5, VertretungsplanAbsence::count());

        Notification::assertNothingSentTo(User::all(), AbwesenheitGemeldet::class);
    }

    public function test_alle_zwei_wochen(): void
    {
        $ich = $this->mitarbeiter(['view absences']);

        $this->serie($ich, ['end' => '2026-11-30', 'wochentage' => [1], 'intervall' => 2])->assertSessionHasNoErrors();

        // Woche des Beginns (ab 12.10.) zählt als erste Woche
        $this->assertSame(['2026-10-26', '2026-11-09', '2026-11-23'], $this->tage($ich));
        $this->assertSame('alle 2 Wochen montags', AbsenceSerie::sole()->rhythmusText());
    }

    public function test_monatlich_letzter_freitag_ueberspringt_feiertage(): void
    {
        $ich = $this->mitarbeiter(['view absences']);

        $this->serie($ich, [
            'end' => '2027-01-31',
            'rhythmus' => 'monatlich',
            'monat_position' => -1,
            'monat_wochentag' => 5,
        ])->assertSessionHasNoErrors();

        // 25.12.2026 ist der 1. Weihnachtsfeiertag
        $this->assertSame(['2026-10-30', '2026-11-27', '2027-01-29'], $this->tage($ich));
        $this->assertSame('jeden letzten Freitag im Monat', AbsenceSerie::sole()->rhythmusText());
    }

    public function test_monatlich_erster_montag(): void
    {
        $ich = $this->mitarbeiter(['view absences']);

        $this->serie($ich, [
            'end' => '2026-12-31',
            'rhythmus' => 'monatlich',
            'monat_position' => 1,
            'monat_wochentag' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-02', '2026-12-07'], $this->tage($ich));
    }

    public function test_belegte_tage_werden_uebersprungen_und_nichts_zusammengefuehrt(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        Absence::factory()->create(['users_id' => $ich->id, 'creator_id' => $ich->id, 'reason' => 'Urlaub', 'start' => '2026-10-20', 'end' => '2026-10-20']);
        Absence::factory()->create(['users_id' => $ich->id, 'creator_id' => $ich->id, 'reason' => 'Fortbildung', 'start' => '2026-10-21', 'end' => '2026-10-21']);

        $this->serie($ich)->assertSessionHas('Meldung', fn (string $m) => str_contains($m, '4 Termine angelegt, 1 übersprungen'));

        $this->assertSame(4, Absence::whereNotNull('serie_id')->count());
        // Die angrenzende Abwesenheit mit gleichem Grund bleibt unverändert ein einzelner Tag
        $this->assertSame(1, Absence::whereNull('serie_id')->where('reason', 'Fortbildung')->whereDate('start', '2026-10-21')->whereDate('end', '2026-10-21')->count());
    }

    public function test_regeln(): void
    {
        $ich = $this->mitarbeiter(['view absences']);

        $this->serie($ich, ['reason' => 'Kind krank'])->assertSessionHasErrors('reason');
        $this->serie($ich, ['start' => '2026-10-13'])->assertSessionHasErrors('start');
        $this->serie($ich, ['end' => '2027-10-15'])->assertSessionHasErrors('end');
        $this->serie($ich, ['wochentage' => []])->assertSessionHasErrors('wochentage');
        $this->serie($ich, ['wochentage' => [6]])->assertSessionHasErrors('wochentage.0');
        $this->serie($ich, ['start' => '2026-10-17', 'end' => '2026-10-17'])->assertSessionHasErrors('rhythmus');

        $this->assertSame(0, Absence::count());
        $this->assertSame(0, AbsenceSerie::count());
    }

    public function test_ohne_wiederkehrend_bleibt_alles_wie_bisher(): void
    {
        $ich = $this->mitarbeiter(['view absences']);

        $this->serie($ich, ['wiederkehrend' => 0, 'reason' => 'krank'])->assertSessionHasNoErrors();

        $absence = Absence::sole();
        $this->assertNull($absence->serie_id);
        $this->assertSame('2026-10-31', $absence->end->toDateString());
        Notification::assertNothingSentTo(User::all(), AbwesenheitsserieGemeldet::class);
    }

    public function test_fremde_person_nur_mit_recht(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $andere = $this->mitarbeiter();

        $this->serie($ich, ['users_id' => $andere->id])->assertSessionHasNoErrors();

        $this->assertSame(0, Absence::where('users_id', $andere->id)->count());
        $this->assertSame($ich->id, AbsenceSerie::sole()->users_id);
    }

    public function test_zukuenftige_termine_loeschen(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $this->serie($ich, ['wochentage' => [4]]); // 15., 22., 29.10.
        $serie = AbsenceSerie::sole();

        Carbon::setTestNow('2026-10-23 08:00:00');
        $fremd = $this->mitarbeiter(['view absences']);
        $this->actingAs($fremd)->delete(route('absences.serie.destroy', $serie))->assertSessionHas('type', 'danger');
        $this->assertSame(3, Absence::count());

        $this->actingAs($ich->fresh())->delete(route('absences.serie.destroy', $serie))->assertSessionHas('type', 'info');

        $this->assertSame(['2026-10-15', '2026-10-22'], $this->tage($ich));
        $this->assertNotNull($serie->fresh()); // Regel bleibt für die vergangenen Termine erhalten
    }

    public function test_uebersicht_und_export_zeigen_serie(): void
    {
        $ich = $this->mitarbeiter(['view absences', 'view old absences']);
        $this->serie($ich);

        $this->actingAs($ich->fresh())->get('/absences')
            ->assertOk()
            ->assertSee('jeden Dienstag und Donnerstag vom 14.10.2026 bis 31.10.2026')
            ->assertSee(route('absences.serie.destroy', AbsenceSerie::sole()));

        $export = new \App\Exports\AbsenceExport(Absence::with('serie')->get());
        $this->assertSame('Serie', last($export->headings()));
        $this->assertSame('jeden Dienstag und Donnerstag vom 14.10.2026 bis 31.10.2026', last($export->map(Absence::first())));
    }
}
