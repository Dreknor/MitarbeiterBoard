<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/** JSON-Endpunkte des Editors inkl. Versionsprüfung. */
class WochenplanEditorTest extends TestCase
{
    use WochenplanTestHilfen;

    private Klasse $klasse;

    private WpPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextcloudFake(false);
        $this->klasse = Klasse::factory()->create();
        $this->plan = $this->klassenplan($this->klasse);
        $this->actingAs($this->paedagoge([$this->klasse]));
    }

    public function test_aufgabe_anlegen_aendern_loeschen(): void
    {
        $fach = $this->fachMitAufgaben($this->plan, []);

        $antwort = $this->postJson(route('wp.aufgabe.store', [$this->plan, $fach]), [
            'aufgabe' => 'Seite 12 lesen', 'dauer' => '20 min', 'niveau' => 2, 'link' => 'https://example.org/blatt',
        ])->assertOk();
        $antwort->assertJsonPath('plan.faecher.0.aufgaben.0.aufgabe', 'Seite 12 lesen')
            ->assertJsonPath('plan.faecher.0.aufgaben.0.niveau', 2)
            ->assertJsonPath('plan.version', 2);

        $aufgabe = WpAufgabe::firstOrFail();
        $this->putJson(route('wp.aufgabe.update', [$this->plan, $aufgabe]), ['aufgabe' => 'Seite 13 lesen', 'basis' => 'Seite 12 lesen'])
            ->assertOk()->assertJsonPath('plan.faecher.0.aufgaben.0.aufgabe', 'Seite 13 lesen');

        $this->deleteJson(route('wp.aufgabe.destroy', [$this->plan, $aufgabe]))->assertOk()->assertJsonCount(0, 'plan.faecher.0.aufgaben');
        $this->assertSoftDeleted($aufgabe);
    }

    public function test_ungueltiger_link_wird_abgelehnt(): void
    {
        $fach = $this->fachMitAufgaben($this->plan, []);
        $this->postJson(route('wp.aufgabe.store', [$this->plan, $fach]), ['aufgabe' => 'X', 'link' => 'javascript:alert(1)'])
            ->assertStatus(422)->assertJsonValidationErrors('link');
    }

    public function test_aufgabe_eines_anderen_plans_im_pfad_ist_404(): void
    {
        $anderer = $this->klassenplan($this->klasse);
        $fremdesFach = $this->fachMitAufgaben($anderer, ['Fremd']);
        $fremdeAufgabe = $fremdesFach->aufgaben->first();

        $this->putJson(route('wp.aufgabe.update', [$this->plan, $fremdeAufgabe]), ['aufgabe' => 'X'])->assertNotFound();
        $this->deleteJson(route('wp.fach.remove', [$this->plan, $fremdesFach]))->assertNotFound();
        $this->postJson(route('wp.aufgabe.store', [$this->plan, $fremdesFach]), ['aufgabe' => 'X'])->assertNotFound();
        $this->assertSame('Fremd', $fremdeAufgabe->fresh()->aufgabe);
    }

    public function test_konflikt_bei_gleichzeitiger_bearbeitung(): void
    {
        $fach = $this->fachMitAufgaben($this->plan, ['Original']);
        $aufgabe = $fach->aufgaben->first();

        // Jemand anderes hat die Aufgabe inzwischen geändert
        $aufgabe->update(['aufgabe' => 'Von Kollegin geändert']);

        $this->putJson(route('wp.aufgabe.update', [$this->plan, $aufgabe]), ['aufgabe' => 'Meine Fassung', 'basis' => 'Original'])
            ->assertStatus(409)
            ->assertJsonPath('plan.faecher.0.aufgaben.0.aufgabe', 'Von Kollegin geändert');

        // Eckdaten mit veralteter Version
        $version = $this->plan->fresh()->version;
        $this->putJson(route('wp.update', $this->plan), ['name' => 'A', 'version' => $version])->assertOk();
        $this->putJson(route('wp.update', $this->plan), ['name' => 'B', 'version' => $version])
            ->assertStatus(409)->assertJsonPath('plan.name', 'A');
    }

    public function test_aufgaben_zwischen_faechern_verschieben(): void
    {
        $deutsch = $this->fachMitAufgaben($this->plan, ['D1', 'D2'], 'Deutsch', 0);
        $mathe = $this->fachMitAufgaben($this->plan, ['M1'], 'Mathe', 1);
        [$d1, $d2] = $deutsch->aufgaben->all();
        $m1 = $mathe->aufgaben->first();

        $this->postJson(route('wp.aufgabe.reorder', $this->plan), ['eintraege' => [
            ['id' => $d2->id, 'wp_plan_fach_id' => $deutsch->id],
            ['id' => $m1->id, 'wp_plan_fach_id' => $mathe->id],
            ['id' => $d1->id, 'wp_plan_fach_id' => $mathe->id],
        ]])->assertOk();

        $this->assertSame($mathe->id, $d1->fresh()->wp_plan_fach_id);
        $this->assertSame(1, $d1->fresh()->sort_order);
        $this->assertSame(0, $d2->fresh()->sort_order);

        $this->postJson(route('wp.fach.reorder', $this->plan), ['ids' => [$mathe->id, $deutsch->id]])->assertOk()
            ->assertJsonPath('plan.faecher.0.name', 'Mathe');
    }

    public function test_sortieren_ignoriert_fremde_ids(): void
    {
        $anderer = $this->klassenplan($this->klasse);
        $fremd = $this->fachMitAufgaben($anderer, ['Fremd']);
        $eigen = $this->fachMitAufgaben($this->plan, ['Eigen']);

        $this->postJson(route('wp.aufgabe.reorder', $this->plan), ['eintraege' => [
            ['id' => $fremd->aufgaben->first()->id, 'wp_plan_fach_id' => $eigen->id],
        ]])->assertOk();

        $this->assertSame($fremd->id, $fremd->aufgaben->first()->fresh()->wp_plan_fach_id);
    }

    public function test_faecher_und_taegliche_uebungen(): void
    {
        $fach = WpFach::factory()->create(['name' => 'Sachkunde']);
        $this->postJson(route('wp.fach.add', $this->plan), ['wp_fach_id' => $fach->id])->assertOk()
            ->assertJsonFragment(['name' => 'Sachkunde']);
        // doppelt hinzufügen legt kein zweites an
        $this->postJson(route('wp.fach.add', $this->plan), ['wp_fach_id' => $fach->id])->assertOk();
        $this->assertSame(1, $this->plan->planFaecher()->where('wp_fach_id', $fach->id)->count());

        $this->postJson(route('wp.taegliche-uebungen.toggle', $this->plan), ['aktiv' => true])->assertOk()->assertJsonPath('plan.taegliche_uebungen_aktiv', true);
        $this->postJson(route('wp.taegliche-uebungen.store', $this->plan), ['aufgabe' => '10 Minuten lesen'])->assertOk()
            ->assertJsonPath('plan.uebungen.0.aufgabe', '10 Minuten lesen');
    }

    public function test_fortschreiben_legt_naechste_woche_an(): void
    {
        $this->fachMitAufgaben($this->plan, ['A1', 'A2']);

        $this->post(route('wp.fortschreiben', $this->plan))->assertRedirect();

        $neu = WpPlan::where('id', '!=', $this->plan->id)->latest('id')->firstOrFail();
        $this->assertTrue($neu->gueltig_von->equalTo($this->plan->gueltig_von->copy()->addWeek()));
        $this->assertSame(2, $neu->aufgaben()->count());
        $this->assertSame($this->klasse->id, $neu->klasse_id);
    }

    public function test_editor_seite_laedt(): void
    {
        $this->fachMitAufgaben($this->plan, ['Lesen']);
        $this->get(route('wp.edit', $this->plan))->assertOk()->assertSee('wpEditor', false)->assertSee('Lesen');
    }
}
