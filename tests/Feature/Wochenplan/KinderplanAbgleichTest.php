<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpAufgabeStatus;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpStatusService;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/** Kinderpläne: Niveau, Abgleich an Ort und Stelle (Status bleibt), angepasste/eigene Aufgaben, Fortschreiben. */
class KinderplanAbgleichTest extends TestCase
{
    use WochenplanTestHilfen;

    private WochenplanService $service;

    private Klasse $klasse;

    private WpPlan $plan;

    private Schueler $kind;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextcloudFake(false);
        $this->service = app(WochenplanService::class);
        $this->klasse = Klasse::factory()->create();
        $this->plan = $this->klassenplan($this->klasse);
        $this->kind = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        $this->actingAs($this->paedagoge([$this->klasse]));
    }

    public function test_kinderplan_mit_niveau_uebernimmt_nur_passende_aufgaben(): void
    {
        $this->fachMitAufgaben($this->plan, [
            ['aufgabe' => 'Für alle'],
            ['aufgabe' => 'Basis', 'niveau' => 1],
            ['aufgabe' => 'Erweitert', 'niveau' => 3],
        ]);

        $kp = $this->service->kinderplanErstellen($this->plan, $this->kind, null, 1);

        $this->assertEqualsCanonicalizing(['Für alle', 'Basis'], $kp->aufgaben()->pluck('aufgabe')->all());
        $this->assertTrue($kp->aufgaben()->get()->every(fn ($a) => $a->synced_from_id !== null));
    }

    public function test_kinderplaene_per_formular_mit_niveau(): void
    {
        $this->fachMitAufgaben($this->plan, [['aufgabe' => 'Basis', 'niveau' => 1], ['aufgabe' => 'Erweitert', 'niveau' => 3]]);
        $zweites = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);

        $this->get(route('wp.schuelerplan.create', $this->plan))->assertOk()->assertSee('Niveau');
        $this->post(route('wp.schuelerplan.store', $this->plan), [
            'schueler_ids' => [$this->kind->id, $zweites->id],
            'niveau' => [$this->kind->id => 3],
        ])->assertRedirect(route('wp.edit', $this->plan));

        $kp = WpPlan::where('schueler_id', $this->kind->id)->firstOrFail();
        $this->assertSame(['Erweitert'], $kp->aufgaben()->pluck('aufgabe')->all());
        $this->assertSame(2, WpPlan::where('schueler_id', $zweites->id)->firstOrFail()->aufgaben()->count());
    }

    public function test_abgleich_aktualisiert_an_ort_und_stelle_und_behaelt_status(): void
    {
        $fach = $this->fachMitAufgaben($this->plan, ['Lesen S. 10', 'Rechnen', 'Wird gestrichen']);
        $kp = $this->service->kinderplanErstellen($this->plan, $this->kind);
        $kindAufgabe = $kp->aufgaben()->where('aufgabe', 'Lesen S. 10')->firstOrFail();
        $angepasst = $kp->aufgaben()->where('aufgabe', 'Rechnen')->firstOrFail();
        app(WpStatusService::class)->setzen($kindAufgabe, $this->kind, WpAufgabeStatus::ERLEDIGT, 'gut', null);

        // Lehrkraft passt eine Aufgabe im Kinderplan an und ergänzt eine eigene
        $this->service->aufgabeAendern($angepasst, ['aufgabe' => 'Rechnen (leichter)']);
        $eigeneFach = $kp->planFaecher()->firstOrFail();
        $this->service->aufgabeAnlegen($eigeneFach, ['aufgabe' => 'Eigene Übung']);

        // Klassenplan ändert sich
        $fach->aufgaben[0]->update(['aufgabe' => 'Lesen S. 12']);
        $fach->aufgaben[1]->update(['aufgabe' => 'Rechnen S. 5']);
        $fach->aufgaben[2]->delete();
        WpAufgabe::create(['wp_plan_fach_id' => $fach->id, 'aufgabe' => 'Neu im Klassenplan', 'sort_order' => 5]);

        $vorschau = $this->service->abgleichVorschau($kp->fresh());
        $this->assertSame(1, $vorschau['summe']['neu']);
        $this->assertSame(1, $vorschau['summe']['aktualisiert']);
        $this->assertSame(1, $vorschau['summe']['entfallen']);
        $this->assertSame(1, $vorschau['summe']['angepasst']);
        $this->assertSame(1, $vorschau['summe']['eigene']);

        $this->postJson(route('wp.abgleich', $kp), ['version' => $kp->fresh()->version])->assertOk();

        $texte = $kp->aufgaben()->pluck('aufgabe')->all();
        $this->assertEqualsCanonicalizing(['Lesen S. 12', 'Rechnen (leichter)', 'Eigene Übung', 'Neu im Klassenplan'], $texte);
        // gleiche Aufgaben-ID → Status bleibt
        $this->assertSame('Lesen S. 12', $kindAufgabe->fresh()->aufgabe);
        $this->assertDatabaseHas('wp_aufgabe_status', ['wp_aufgabe_id' => $kindAufgabe->id, 'status' => 'erledigt', 'notiz' => 'gut']);
    }

    public function test_abgleich_kann_angepasste_und_eigene_ersetzen(): void
    {
        $this->fachMitAufgaben($this->plan, ['Original']);
        $kp = $this->service->kinderplanErstellen($this->plan, $this->kind);
        $this->service->aufgabeAendern($kp->aufgaben()->firstOrFail(), ['aufgabe' => 'Angepasst']);
        $this->service->aufgabeAnlegen($kp->planFaecher()->firstOrFail(), ['aufgabe' => 'Eigene']);

        $this->service->abgleichen($kp->fresh(), null, false, false);

        $this->assertSame(['Original'], $kp->aufgaben()->pluck('aufgabe')->all());
    }

    public function test_abgleich_nur_fuer_kinderplaene(): void
    {
        $this->getJson(route('wp.abgleich.vorschau', $this->plan))->assertStatus(422);
    }

    public function test_fortschreiben_mit_kinderplaenen_verknuepft_neu(): void
    {
        $this->fachMitAufgaben($this->plan, ['A']);
        $kp = $this->service->kinderplanErstellen($this->plan, $this->kind);
        $this->service->aufgabeAnlegen($kp->planFaecher()->firstOrFail(), ['aufgabe' => 'Individuell']);

        $neu = $this->service->fortschreiben($this->plan->fresh(), true, auth()->user());

        $neuerKp = WpPlan::where('parent_plan_id', $neu->id)->where('schueler_id', $this->kind->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(['A', 'Individuell'], $neuerKp->aufgaben()->pluck('aufgabe')->all());
        $neuA = $neu->aufgaben()->where('aufgabe', 'A')->firstOrFail();
        $this->assertSame($neuA->id, $neuerKp->aufgaben()->where('aufgabe', 'A')->firstOrFail()->synced_from_id);
        $this->assertTrue($neuerKp->gueltig_von->equalTo($neu->gueltig_von));
    }

    public function test_zeitraum_des_klassenplans_gilt_fuer_kinderplaene(): void
    {
        $kp = $this->service->kinderplanErstellen($this->plan, $this->kind);

        $this->putJson(route('wp.update', $this->plan), [
            'gueltig_von' => '2026-12-07', 'gueltig_bis' => '2026-12-11', 'version' => $this->plan->fresh()->version,
        ])->assertOk();

        $this->assertSame('2026-12-07', $kp->fresh()->gueltig_von->toDateString());
    }
}
