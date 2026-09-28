<?php

namespace Tests\Feature\Procedure;

use App\Mail\newStepMail;
use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\RecurringProcedure;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests für wiederkehrende Prozesse.
 */
class ProcedureRecurringTest extends TestCase
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_admin_sieht_recurring_index(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $rp = RecurringProcedure::factory()->create(['name' => 'Jährliche Belehrung']);

        // Phase 4: Wiederkehrende Prozesse sind ein Tab der Übersicht
        $this->get('/procedure/recurring')->assertRedirect(url('procedure') . '#automation');
        $this->get('/procedure')->assertOk()->assertSee('Jährliche Belehrung');
    }

    public function test_nutzer_ohne_manage_wird_auf_recurring_abgewiesen(): void
    {
        $this->actingAsWithPermission('view assigned procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Nicht erlaubt',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'datum',
            'month'           => 3,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('recurring_procedures', ['name' => 'Nicht erlaubt']);
    }

    // ─── Anlegen ──────────────────────────────────────────────────────────────

    public function test_admin_kann_recurring_mit_datum_typ_anlegen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Monatlicher Report',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'datum',
            'month'           => 3,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recurring_procedures', [
            'name'            => 'Monatlicher Report',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'datum',
            'month'           => 3,
        ]);
    }

    public function test_admin_kann_recurring_mit_vor_ferien_typ_anlegen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Vor-Ferien-Checkliste',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'vor_ferien',
            'wochen'          => 2,
            'ferien'          => 'Sommerferien',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_procedures', [
            'name'            => 'Vor-Ferien-Checkliste',
            'faelligkeit_typ' => 'vor_ferien',
            'wochen'          => 2,
            'ferien'          => 'Sommerferien',
        ]);
    }

    public function test_admin_kann_recurring_mit_nach_ferien_typ_anlegen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Nach-Ferien-Onboarding',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'nach_ferien',
            'wochen'          => 1,
            'ferien'          => 'Osterferien',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_procedures', [
            'faelligkeit_typ' => 'nach_ferien',
            'ferien'          => 'Osterferien',
        ]);
    }

    public function test_datum_typ_ohne_monat_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Kein Monat',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'datum',
            'month'           => null,
        ]);

        $response->assertSessionHasErrors(['month']);
    }

    public function test_vor_ferien_typ_ohne_wochen_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Keine Wochen',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'vor_ferien',
            'wochen'          => null,
            'ferien'          => 'Sommerferien',
        ]);

        $response->assertSessionHasErrors(['wochen']);
    }

    public function test_vor_ferien_typ_ohne_ferien_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Keine Ferien',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'vor_ferien',
            'wochen'          => 2,
            'ferien'          => null,
        ]);

        $response->assertSessionHasErrors(['ferien']);
    }

    public function test_ungueltiger_faelligkeit_typ_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Ungültiger Typ',
            'procedure_id'    => $vorlage->id,
            'faelligkeit_typ' => 'ungueltig',
        ]);

        $response->assertSessionHasErrors(['faelligkeit_typ']);
    }

    public function test_laufender_prozess_ist_als_vorlage_nicht_erlaubt(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $laufend = Procedure::factory()->gestartet()->create();

        $response = $this->post('/procedure/recurring', [
            'name'            => 'Falsche Vorlage',
            'procedure_id'    => $laufend->id,
            'faelligkeit_typ' => 'datum',
            'month'           => 3,
        ]);

        $response->assertSessionHasErrors(['procedure_id']);
    }

    // ─── Löschen ──────────────────────────────────────────────────────────────

    public function test_admin_mit_delete_permission_kann_recurring_loeschen(): void
    {
        $this->actingAsWithPermission('manage procedures', 'delete procedures');
        $rp = RecurringProcedure::factory()->create();

        $response = $this->delete("/procedure/recurring/{$rp->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('recurring_procedures', ['id' => $rp->id]);
    }

    public function test_admin_ohne_delete_permission_kann_recurring_nicht_loeschen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $rp = RecurringProcedure::factory()->create();

        $response = $this->delete("/procedure/recurring/{$rp->id}");

        $response->assertRedirect();
        $response->assertSessionHas('type', 'danger');
        $this->assertDatabaseHas('recurring_procedures', ['id' => $rp->id]);
    }

    // ─── Manueller Start ──────────────────────────────────────────────────────

    public function test_admin_kann_recurring_manuell_starten(): void
    {
        Mail::fake();

        $this->actingAsWithPermission('manage procedures');

        $position = Positions::factory()->create();
        $empfaenger = User::factory()->create();
        $position->users()->attach($empfaenger->id);

        $vorlage = Procedure::factory()->vorlage()->create();
        $step = \App\Models\Procedure_Step::factory()->create([
            'procedure_id' => $vorlage->id,
            'position_id'  => $position->id,
            'parent'       => null,
        ]);

        $rp = RecurringProcedure::factory()->create([
            'procedure_id' => $vorlage->id,
        ]);

        $response = $this->post("/procedure/recurring/{$rp->id}/trigger");

        $response->assertRedirect();

        $gestartet = Procedure::where('name', $rp->name . ' - ' . now()->format('Y'))->firstOrFail();
        $neuerStep = $gestartet->steps()->first();

        // Fälligkeit = Startdatum + Dauer des Schritts (nicht sofort überfällig)
        $this->assertSame(
            now()->addDays($step->durationDays)->toDateString(),
            $neuerStep->endDate->toDateString()
        );
        $this->assertTrue($neuerStep->users->contains('id', $empfaenger->id));
        $this->assertNotNull($rp->fresh()->last_triggered_at);
    }

    public function test_get_start_route_mutiert_nicht_mehr(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $rp = RecurringProcedure::factory()->create();

        $this->get("/procedure/recurring/{$rp->id}/start/true")->assertNotFound();
        $this->assertDatabaseMissing('procedures', ['name' => $rp->name . ' - ' . now()->format('Y')]);
    }

    public function test_recurring_start_via_post_route(): void
    {
        Mail::fake();
        $this->actingAsWithPermission('manage procedures');

        $position   = Positions::factory()->create();
        $empfaenger = User::factory()->create();
        $position->users()->attach($empfaenger->id);

        $vorlage = Procedure::factory()->vorlage()->create();
        \App\Models\Procedure_Step::factory()->create([
            'procedure_id' => $vorlage->id,
            'position_id'  => $position->id,
            'parent'       => null,
        ]);

        $rp = RecurringProcedure::factory()->create([
            'procedure_id' => $vorlage->id,
        ]);

        // POST Alias (Phase 0 – neue Route)
        $response = $this->post("/procedure/recurring/{$rp->id}/trigger");

        $response->assertRedirect();
        $this->assertDatabaseHas('procedures', [
            'name' => $rp->name . ' - ' . now()->format('Y'),
        ]);
    }
}


