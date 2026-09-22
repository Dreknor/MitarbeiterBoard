<?php

namespace Tests\Unit\Models\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use Carbon\Carbon;
use Tests\TestCase;

class WpPlanTest extends TestCase
{
    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function test_scopeKlassenplaene_filtert_korrekt(): void
    {
        WpPlan::factory()->alsKlassenplan()->create();
        WpPlan::factory()->alsVorlage()->create();

        $schueler = Schueler::factory()->create();
        $parent   = WpPlan::factory()->alsKlassenplan()->create();
        WpPlan::factory()->alsSchuelerplan($parent)->create(['schueler_id' => $schueler->id]);

        $result = WpPlan::klassenplaene()->get();
        $this->assertCount(2, $result); // 2 Klassenpläne, keine Vorlage, kein Schülerplan
    }

    public function test_scopeSchuelerplaene_filtert_korrekt(): void
    {
        WpPlan::factory()->alsKlassenplan()->create();
        $parent   = WpPlan::factory()->alsKlassenplan()->create();
        $schueler = Schueler::factory()->create();
        WpPlan::factory()->alsSchuelerplan($parent)->create(['schueler_id' => $schueler->id]);

        $result = WpPlan::schuelerplaene()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopeVorlagen_filtert_korrekt(): void
    {
        WpPlan::factory()->alsKlassenplan()->create();
        WpPlan::factory()->alsVorlage()->create();
        WpPlan::factory()->alsVorlage()->create();

        $result = WpPlan::vorlagen()->get();
        $this->assertCount(2, $result);
    }

    public function test_scopeAktuell_filtert_aktuelle_woche(): void
    {
        // Aktueller Plan
        WpPlan::factory()->create([
            'gueltig_von' => now()->startOfWeek(),
            'gueltig_bis' => now()->endOfWeek(),
        ]);

        // Vergangener Plan
        WpPlan::factory()->create([
            'gueltig_von' => now()->subMonths(2)->startOfWeek(),
            'gueltig_bis' => now()->subMonths(2)->endOfWeek(),
        ]);

        $result = WpPlan::aktuell()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopeFuerKlasse_filtert_nach_klasse(): void
    {
        $klasse1 = Klasse::factory()->create();
        $klasse2 = Klasse::factory()->create();

        WpPlan::factory()->create(['klasse_id' => $klasse1->id]);
        WpPlan::factory()->create(['klasse_id' => $klasse2->id]);

        $result = WpPlan::fuerKlasse($klasse1->id)->get();
        $this->assertCount(1, $result);
    }

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_plan_hat_klasse_relation(): void
    {
        $klasse = Klasse::factory()->create();
        $plan   = WpPlan::factory()->create(['klasse_id' => $klasse->id]);

        $this->assertEquals($klasse->id, $plan->klasse->id);
    }

    public function test_plan_hat_parentPlan_relation(): void
    {
        $schueler   = Schueler::factory()->create();
        $parent     = WpPlan::factory()->alsKlassenplan()->create();
        $kinderPlan = WpPlan::factory()->alsSchuelerplan($parent)->create([
            'schueler_id' => $schueler->id,
        ]);

        $this->assertEquals($parent->id, $kinderPlan->parentPlan->id);
    }

    public function test_plan_hat_kinderPlaene_relation(): void
    {
        $parent     = WpPlan::factory()->alsKlassenplan()->create();
        $schueler   = Schueler::factory()->create();
        WpPlan::factory()->alsSchuelerplan($parent)->create(['schueler_id' => $schueler->id]);

        $this->assertCount(1, $parent->kinderPlaene);
    }

    public function test_plan_hat_formatvorlage_relation(): void
    {
        $formatvorlage = WpFormatvorlage::factory()->create();
        $plan          = WpPlan::factory()->create(['formatvorlage_id' => $formatvorlage->id]);

        $this->assertEquals($formatvorlage->id, $plan->formatvorlage->id);
    }

    public function test_planFaecher_sind_nach_sort_order_sortiert(): void
    {
        $plan  = WpPlan::factory()->create();
        $fach1 = WpFach::factory()->create(['name' => 'Dritter', 'sort_order' => 3]);
        $fach2 = WpFach::factory()->create(['name' => 'Erster',  'sort_order' => 1]);

        WpPlanFach::factory()->create(['wp_plan_id' => $plan->id, 'wp_fach_id' => $fach1->id, 'sort_order' => 3]);
        WpPlanFach::factory()->create(['wp_plan_id' => $plan->id, 'wp_fach_id' => $fach2->id, 'sort_order' => 1]);

        $planFaecher = $plan->planFaecher()->get();
        $this->assertEquals(1, $planFaecher->first()->sort_order);
    }

    // ─── Helper-Methoden ─────────────────────────────────────────────────────

    public function test_isKlassenplan_gibt_true_fuer_klassenplan(): void
    {
        $plan = WpPlan::factory()->alsKlassenplan()->create();
        $this->assertTrue($plan->isKlassenplan());
    }

    public function test_isKlassenplan_gibt_false_fuer_vorlage(): void
    {
        $plan = WpPlan::factory()->alsVorlage()->create();
        $this->assertFalse($plan->isKlassenplan());
    }

    public function test_isSchuelerplan_gibt_true_fuer_schuelerplan(): void
    {
        $parent   = WpPlan::factory()->alsKlassenplan()->create();
        $schueler = Schueler::factory()->create();
        $plan     = WpPlan::factory()->alsSchuelerplan($parent)->create([
            'schueler_id' => $schueler->id,
        ]);

        $this->assertTrue($plan->isSchuelerplan());
    }

    public function test_isVorlage_gibt_true_fuer_vorlage(): void
    {
        $plan = WpPlan::factory()->alsVorlage()->create();
        $this->assertTrue($plan->isVorlage());
    }

    // ─── getEffectiveFormatvorlage ────────────────────────────────────────────

    public function test_getEffectiveFormatvorlage_gibt_eigene_formatvorlage(): void
    {
        $fv   = WpFormatvorlage::factory()->create(['name' => 'Eigene']);
        $plan = WpPlan::factory()->create(['formatvorlage_id' => $fv->id]);

        $result = $plan->getEffectiveFormatvorlage();
        $this->assertEquals('Eigene', $result->name);
    }

    public function test_getEffectiveFormatvorlage_fallback_auf_default(): void
    {
        $defaultFv = WpFormatvorlage::factory()->create(['is_default' => true, 'name' => 'Standard']);
        $plan      = WpPlan::factory()->create(['formatvorlage_id' => null]);

        $result = $plan->getEffectiveFormatvorlage();
        $this->assertEquals('Standard', $result->name);
    }

    public function test_getEffectiveFormatvorlage_fallback_auf_ersten_eintrag(): void
    {
        $fv   = WpFormatvorlage::factory()->create(['is_default' => false, 'name' => 'Erste']);
        $plan = WpPlan::factory()->create(['formatvorlage_id' => null]);

        $result = $plan->getEffectiveFormatvorlage();
        $this->assertNotNull($result);
    }

    // ─── Typ-Accessor ────────────────────────────────────────────────────────

    public function test_typ_accessor_fuer_klassenplan(): void
    {
        $plan = WpPlan::factory()->alsKlassenplan()->create();
        $this->assertEquals('Klassenplan', $plan->typ);
    }

    public function test_typ_accessor_fuer_vorlage(): void
    {
        $plan = WpPlan::factory()->alsVorlage()->create();
        $this->assertEquals('Vorlage', $plan->typ);
    }

    public function test_typ_accessor_fuer_schuelerplan(): void
    {
        $parent   = WpPlan::factory()->alsKlassenplan()->create();
        $schueler = Schueler::factory()->create();
        $plan     = WpPlan::factory()->alsSchuelerplan($parent)->create([
            'schueler_id' => $schueler->id,
        ]);

        $this->assertEquals('Individuell', $plan->typ);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_gueltig_von_ist_date(): void
    {
        $plan = WpPlan::factory()->create(['gueltig_von' => '2026-03-09']);
        $this->assertInstanceOf(Carbon::class, $plan->gueltig_von);
    }

    public function test_is_vorlage_ist_boolean(): void
    {
        $plan = WpPlan::factory()->alsVorlage()->create();
        $this->assertIsBool($plan->is_vorlage);
        $this->assertTrue($plan->is_vorlage);
    }

    // ─── duplizieren ─────────────────────────────────────────────────────────

    public function test_duplizieren_erstellt_neuen_plan(): void
    {
        $creator = User::factory()->create();
        $this->actingAs($creator);

        $plan = WpPlan::factory()->create(['name' => 'Original-Plan']);
        $dup  = $plan->duplizieren(['name' => 'Kopie']);

        $this->assertNotEquals($plan->id, $dup->id);
        $this->assertEquals('Kopie', $dup->name);
        $this->assertDatabaseHas('wp_plaene', ['name' => 'Kopie']);
    }

    public function test_duplizieren_kopiert_faecher_und_aufgaben(): void
    {
        $creator = User::factory()->create();
        $this->actingAs($creator);

        $plan        = WpPlan::factory()->create();
        $fach        = WpFach::factory()->create();
        $planFach    = WpPlanFach::factory()->create([
            'wp_plan_id' => $plan->id,
            'wp_fach_id' => $fach->id,
            'sort_order' => 1,
        ]);
        WpAufgabe::factory()->create(['wp_plan_fach_id' => $planFach->id]);

        $dup = $plan->duplizieren();
        $dup->load('planFaecher.aufgaben');

        $this->assertCount(1, $dup->planFaecher);
        $this->assertCount(1, $dup->planFaecher->first()->aufgaben);
    }

    // ─── erstelleSchuelerplan ────────────────────────────────────────────────

    public function test_erstelleSchuelerplan_erstellt_schuelerspezifischen_plan(): void
    {
        $creator  = User::factory()->create();
        $this->actingAs($creator);

        $klasse   = Klasse::factory()->create();
        $plan     = WpPlan::factory()->create(['klasse_id' => $klasse->id]);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $schuelerPlan = $plan->erstelleSchuelerplan($schueler);

        $this->assertEquals($schueler->id, $schuelerPlan->schueler_id);
        $this->assertEquals($plan->id, $schuelerPlan->parent_plan_id);
        $this->assertFalse((bool) $schuelerPlan->is_vorlage);
    }
}

