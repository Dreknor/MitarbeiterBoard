<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpPlan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/** Klassenregel: Pädagog:innen sehen und bearbeiten nur die Pläne ihrer Klassen. */
class WochenplanZugriffTest extends TestCase
{
    use WochenplanTestHilfen;

    public function test_uebersicht_zeigt_nur_plaene_der_eigenen_klassen(): void
    {
        $a = Klasse::factory()->create(['name' => '3a']);
        $b = Klasse::factory()->create(['name' => '4b']);
        $this->klassenplan($a, ['name' => 'Plan der 3a']);
        $this->klassenplan($b, ['name' => 'Plan der 4b']);

        $this->actingAs($this->paedagoge([$a]))
            ->get(route('wp.index'))
            ->assertOk()
            ->assertSee('Plan der 3a')
            ->assertDontSee('Plan der 4b');
    }

    public function test_fremder_plan_ist_gesperrt(): void
    {
        $a = Klasse::factory()->create();
        $b = Klasse::factory()->create();
        $fremd = $this->klassenplan($b);
        $this->actingAs($this->paedagoge([$a]));

        $this->get(route('wp.edit', $fremd))->assertForbidden();
        $this->get(route('wp.export.pdf', $fremd))->assertForbidden();
        $this->putJson(route('wp.update', $fremd), ['name' => 'X'])->assertForbidden();
        $this->delete(route('wp.destroy', $fremd))->assertForbidden();
        $this->postJson(route('wp.fach.add', $fremd), ['wp_fach_id' => 1])->assertForbidden();
        $this->assertDatabaseHas('wp_plaene', ['id' => $fremd->id, 'deleted_at' => null]);
    }

    public function test_eigener_plan_ist_bearbeitbar(): void
    {
        $a = Klasse::factory()->create();
        $plan = $this->klassenplan($a);
        $this->actingAs($this->paedagoge([$a]));

        $this->get(route('wp.edit', $plan))->assertOk()->assertSee($plan->name);
        $this->putJson(route('wp.update', $plan), ['name' => 'Neuer Name', 'version' => $plan->version])
            ->assertOk()->assertJsonPath('plan.name', 'Neuer Name');
    }

    public function test_kinderplan_folgt_der_aktuellen_klasse_des_kindes(): void
    {
        $alt = Klasse::factory()->create();
        $neu = Klasse::factory()->create();
        $kind = Schueler::factory()->create(['klasse_id' => $alt->id]);
        $klassenplan = $this->klassenplan($alt);
        $kinderplan = app(\App\Services\Wochenplan\WochenplanService::class)->kinderplanErstellen($klassenplan, $kind);

        $lehrerAlt = $this->paedagoge([$alt]);
        $lehrerNeu = $this->paedagoge([$neu]);

        $this->actingAs($lehrerAlt)->get(route('wp.edit', $kinderplan))->assertOk();
        $this->actingAs($lehrerNeu)->get(route('wp.edit', $kinderplan))->assertForbidden();

        $kind->update(['klasse_id' => $neu->id]);

        $this->actingAs($lehrerNeu)->get(route('wp.edit', $kinderplan->fresh()))->assertOk();
        $this->actingAs($lehrerAlt)->get(route('wp.edit', $kinderplan->fresh()))->assertForbidden();
    }

    public function test_plan_ohne_klasse_nur_fuer_ersteller(): void
    {
        $ersteller = $this->paedagoge([]);
        $plan = WpPlan::factory()->create(['klasse_id' => null, 'created_by' => $ersteller->id]);
        $andere = $this->paedagoge([Klasse::factory()->create()]);

        $this->actingAs($ersteller)->get(route('wp.edit', $plan))->assertOk();
        $this->actingAs($andere)->get(route('wp.edit', $plan))->assertForbidden();
    }

    public function test_neuer_plan_braucht_eine_eigene_klasse(): void
    {
        $eigene = Klasse::factory()->create();
        $fremde = Klasse::factory()->create();
        $this->actingAs($this->paedagoge([$eigene]));

        $basis = ['name' => 'Plan', 'gueltig_von' => '2026-11-23', 'gueltig_bis' => '2026-11-27', 'selbsteinschaetzung' => 0];

        $this->post(route('wp.store'), $basis)->assertSessionHasErrors('klasse_id');
        $this->post(route('wp.store'), $basis + ['klasse_id' => $fremde->id])->assertSessionHasErrors('klasse_id');
        $this->post(route('wp.store'), $basis + ['klasse_id' => $eigene->id])->assertRedirect();
        $this->assertDatabaseHas('wp_plaene', ['name' => 'Plan', 'klasse_id' => $eigene->id]);
    }

    public function test_sonderrecht_sieht_alle_klassen(): void
    {
        $b = Klasse::factory()->create();
        $plan = $this->klassenplan($b, ['name' => 'Plan B']);
        $admin = $this->paedagoge([], ['create wochenplan', 'manage all wochenplaene']);

        $this->actingAs($admin)->get(route('wp.index'))->assertOk()->assertSee('Plan B');
        $this->actingAs($admin)->get(route('wp.edit', $plan))->assertOk();
    }

    public function test_nur_lesen_darf_nicht_aendern(): void
    {
        $a = Klasse::factory()->create();
        $plan = $this->klassenplan($a);
        $leser = $this->paedagoge([$a], ['view wochenplan']);

        $this->actingAs($leser)->get(route('wp.show', $plan))->assertOk()->assertSee('nur zum Lesen');
        $this->actingAs($leser)->get(route('wp.edit', $plan))->assertForbidden();
        $this->actingAs($leser)->putJson(route('wp.update', $plan), ['name' => 'X'])->assertForbidden();
    }

    public function test_vorlagen_fuer_alle_aendern_nur_ersteller(): void
    {
        $ersteller = $this->paedagoge([]);
        $vorlage = WpPlan::factory()->alsVorlage()->create(['created_by' => $ersteller->id, 'name' => 'Basis', 'vorlage_name' => 'Basis']);
        $andere = $this->paedagoge([Klasse::factory()->create()]);

        $this->actingAs($andere)->get(route('wp.vorlagen.index'))->assertOk()->assertSee('Basis');
        $this->actingAs($andere)->get(route('wp.show', $vorlage))->assertOk();
        $this->actingAs($andere)->putJson(route('wp.update', $vorlage), ['name' => 'X'])->assertForbidden();
        $this->actingAs($andere)->delete(route('wp.vorlagen.destroy', $vorlage))->assertForbidden();
        $this->actingAs($ersteller)->delete(route('wp.vorlagen.destroy', $vorlage))->assertRedirect();
        $this->assertSoftDeleted($vorlage);
    }

    public function test_klassenplan_loeschen_loescht_kinderplaene(): void
    {
        $a = Klasse::factory()->create();
        $plan = $this->klassenplan($a);
        $kind = app(\App\Services\Wochenplan\WochenplanService::class)
            ->kinderplanErstellen($plan, Schueler::factory()->create(['klasse_id' => $a->id]));

        $this->actingAs($this->paedagoge([$a]))->delete(route('wp.destroy', $plan))->assertRedirect(route('wp.index'));

        $this->assertSoftDeleted($plan);
        $this->assertSoftDeleted($kind);
    }

    public function test_altsystem_routen_existieren_nicht_mehr(): void
    {
        Permission::findOrCreate('create wochenplan');
        $this->actingAsWithPermission('create wochenplan');

        $this->get('/wptask/1/edit')->assertNotFound();
        $this->delete('/wochenplan/media/1/remove')->assertStatus(404);
    }
}
