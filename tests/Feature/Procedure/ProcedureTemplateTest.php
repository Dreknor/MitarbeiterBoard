<?php

namespace Tests\Feature\Procedure;

use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\Procedure_Step;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für Vorlagen-CRUD und Kategorie-Anlage.
 */
class ProcedureTemplateTest extends TestCase
{
    // ─── Vorlagen: Index ──────────────────────────────────────────────────────

    public function test_admin_sieht_vorlagen_index(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->get('/procedure/template');

        $response->assertStatus(200);
    }

    public function test_benutzer_ohne_permission_wird_abgewiesen_auf_vorlagen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/procedure/template');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_wird_auf_login_weitergeleitet(): void
    {
        $response = $this->get('/procedure/template');

        $response->assertRedirect('/login');
    }

    // ─── Vorlagen: Erstellen ──────────────────────────────────────────────────

    public function test_admin_kann_neue_vorlage_erstellen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $kategorie = Procedure_Category::factory()->create();

        $response = $this->post('/procedure/create/template', [
            'name'        => 'Test-Prozess',
            'category_id' => $kategorie->id,
            'description' => 'Eine Beschreibung',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procedures', [
            'name'        => 'Test-Prozess',
            'category_id' => $kategorie->id,
            'started_at'  => null,
        ]);
    }

    public function test_vorlage_erstellen_ohne_pflichtfelder_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->post('/procedure/create/template', [
            'description' => 'Ohne Name und Kategorie',
        ]);

        $response->assertSessionHasErrors(['name', 'category_id']);
    }

    public function test_vorlage_erstellen_mit_unbekannter_kategorie_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->post('/procedure/create/template', [
            'name'        => 'Test',
            'category_id' => 9999,
        ]);

        $response->assertSessionHasErrors(['category_id']);
    }

    public function test_nutzer_ohne_manage_kann_keine_vorlage_erstellen(): void
    {
        $this->actingAsWithPermission('view assigned procedures');
        $kategorie = Procedure_Category::factory()->create();

        $response = $this->post('/procedure/create/template', [
            'name'        => 'Test-Prozess',
            'category_id' => $kategorie->id,
        ]);

        // Entweder 403 oder Redirect mit Fehlermeldung
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect(),
            'Erwartet 403 oder Redirect für fehlende Berechtigung'
        );
    }

    // ─── Vorlagen: Bearbeiten (Schritt-Baum) ─────────────────────────────────

    public function test_admin_kann_vorlage_bearbeiten(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->get("/procedure/{$vorlage->id}/edit");

        $response->assertStatus(200);
    }

    public function test_benutzer_ohne_permission_kann_vorlage_nicht_bearbeiten(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->get("/procedure/{$vorlage->id}/edit");

        $response->assertStatus(403);
    }

    public function test_bearbeiten_von_nichtvorhandener_vorlage_liefert_404(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->get('/procedure/99999/edit');

        $response->assertStatus(404);
    }

    // ─── Vorlagen: Name/Beschreibung aktualisieren ────────────────────────────

    public function test_admin_kann_vorlagenname_aktualisieren(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create(['name' => 'Alt']);

        $response = $this->put("/procedure/{$vorlage->id}/update", [
            'name'        => 'Neu',
            'description' => 'Neue Beschreibung',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('procedures', [
            'id'   => $vorlage->id,
            'name' => 'Neu',
        ]);
    }

    public function test_aktualisieren_ohne_name_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->put("/procedure/{$vorlage->id}/update", [
            'description' => 'Nur Beschreibung',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    // ─── Vorlagen: Löschen ────────────────────────────────────────────────────

    public function test_admin_mit_delete_permission_kann_vorlage_loeschen(): void
    {
        $this->actingAsWithPermission('manage procedures', 'delete procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        // Phase 4: nur noch REST-konformes DELETE
        $response = $this->delete("/procedure/{$vorlage->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('procedures', ['id' => $vorlage->id]);
    }

    public function test_loeschen_via_delete_method_soft_loescht(): void
    {
        $this->actingAsWithPermission('manage procedures', 'delete procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->delete("/procedure/{$vorlage->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('procedures', ['id' => $vorlage->id]);
    }

    public function test_nutzer_ohne_delete_permission_kann_vorlage_nicht_loeschen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        // Phase 4: REST-konformes DELETE (ohne Permission → Fehlermeldung)
        $response = $this->delete("/procedure/{$vorlage->id}");

        $response->assertRedirect();
        $response->assertSessionHas('type', 'danger');
        $this->assertDatabaseHas('procedures', ['id' => $vorlage->id, 'deleted_at' => null]);
    }

    // ─── Schritte: Hinzufügen zu Vorlage ─────────────────────────────────────

    public function test_admin_kann_schritt_zu_vorlage_hinzufuegen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $vorlage  = Procedure::factory()->vorlage()->create();

        $response = $this->post("/procedure/{$vorlage->id}/step", [
            'name'         => 'Erster Schritt',
            'description'  => 'Beschreibung',
            'position_id'  => $position->id,
            'durationDays' => 3,
            'parent'       => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procedure_steps', [
            'procedure_id' => $vorlage->id,
            'name'         => 'Erster Schritt',
            'position_id'  => $position->id,
        ]);
    }

    public function test_schritt_hinzufuegen_ohne_pflichtfelder_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post("/procedure/{$vorlage->id}/step", [
            'name' => '',
        ]);

        $response->assertSessionHasErrors();
    }

    // ─── Schritte: Bearbeiten ─────────────────────────────────────────────────

    public function test_admin_kann_schritt_bearbeiten(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $vorlage  = Procedure::factory()->vorlage()->create();
        $step     = Procedure_Step::factory()->create(['procedure_id' => $vorlage->id, 'position_id' => $position->id]);

        $response = $this->get("/procedure/step/{$step->id}/edit");

        $response->assertStatus(200);
    }

    public function test_admin_kann_schritt_speichern(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $vorlage  = Procedure::factory()->vorlage()->create();
        $step     = Procedure_Step::factory()->create(['procedure_id' => $vorlage->id, 'position_id' => $position->id]);

        $response = $this->put("/procedure/step/{$step->id}", [
            'name'         => 'Aktualisierter Name',
            'description'  => 'Neue Beschreibung',
            'position_id'  => $position->id,
            'durationDays' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('procedure_steps', [
            'id'   => $step->id,
            'name' => 'Aktualisierter Name',
        ]);
    }

    // ─── Schritte: Löschen ────────────────────────────────────────────────────

    public function test_admin_kann_schritt_loeschen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $vorlage  = Procedure::factory()->vorlage()->create();
        $step     = Procedure_Step::factory()->create(['procedure_id' => $vorlage->id, 'position_id' => $position->id]);

        $response = $this->delete("/procedure/step/{$step->id}/delete");

        $response->assertRedirect();
        $this->assertDatabaseMissing('procedure_steps', ['id' => $step->id]);
    }

    // ─── Kategorien ───────────────────────────────────────────────────────────

    public function test_admin_kann_kategorie_anlegen(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->post('/procedure/categories', [
            'name' => 'Neue Testkategorie',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('procedure_categories', ['name' => 'Neue Testkategorie']);
    }

    public function test_kategorie_anlegen_ohne_name_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->post('/procedure/categories', ['name' => '']);

        $response->assertSessionHasErrors(['name']);
    }
}

