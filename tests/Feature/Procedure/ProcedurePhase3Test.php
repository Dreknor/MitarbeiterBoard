<?php

namespace Tests\Feature\Procedure;

use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\Procedure_Step;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-3-Tests:
 *  - Drag-&-Drop Schritt-Reihenfolge (B-09 / reorder endpoint)
 *  - AJAX Schritt erledigen (B-15)
 *  - AJAX Schritt wieder öffnen (B-16)
 *  - Vorlage duplizieren (B-05)
 */
class ProcedurePhase3Test extends TestCase
{
    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function erstelleVorlage(User $user): array
    {
        $kategorie = Procedure_Category::factory()->create();
        $position  = Positions::factory()->create();

        $vorlage = Procedure::factory()->create([
            'name'        => 'Vorlage',
            'category_id' => $kategorie->id,
            'author_id'   => $user->id,
            'started_at'  => null,
        ]);

        $step = Procedure_Step::factory()->create([
            'procedure_id' => $vorlage->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 0,
        ]);

        return compact('vorlage', 'position', 'step', 'kategorie');
    }

    private function erstelleGestarteten(User $user): array
    {
        $kategorie = Procedure_Category::factory()->create();
        $position  = Positions::factory()->create();

        $laufend = Procedure::factory()->gestartet()->create([
            'name'        => 'Laufend',
            'category_id' => $kategorie->id,
            'author_id'   => $user->id,
        ]);

        $step = Procedure_Step::factory()->create([
            'procedure_id' => $laufend->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 0,
            'durationDays' => 5,
            'endDate'      => now()->addDays(5),
            'done'         => false,
        ]);
        $step->users()->attach($user->id);

        return compact('laufend', 'position', 'step', 'kategorie');
    }

    // ─── AJAX Schritt erledigen (B-15) ───────────────────────────────────────

    public function test_step_complete_ajax_erledigt_schritt(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('complete own procedure steps');

        ['laufend' => $laufend, 'step' => $step] = $this->erstelleGestarteten($user);

        $response = $this->postJson("/procedure/steps/{$step->id}/complete");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Schritt als erledigt markiert.']);

        $this->assertDatabaseHas('procedure_steps', [
            'id'   => $step->id,
            'done' => true,
        ]);
    }

    public function test_step_complete_ajax_ohne_berechtigung_gibt_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other);
        $other->givePermissionTo('complete own procedure steps');

        ['step' => $step] = $this->erstelleGestarteten($owner);
        // $other ist nicht am step zugewiesen → kein eigener Schritt

        $response = $this->postJson("/procedure/steps/{$step->id}/complete");

        $response->assertStatus(403);
    }

    public function test_step_complete_ajax_wenn_schon_erledigt(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('manage procedures');

        ['laufend' => $laufend, 'step' => $step] = $this->erstelleGestarteten($user);
        $step->update(['done' => true, 'completed_at' => now()]);

        $response = $this->postJson("/procedure/steps/{$step->id}/complete");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Schritt bereits erledigt.');
    }

    // ─── AJAX Schritt wieder öffnen (B-16) ───────────────────────────────────

    public function test_step_reopen_ajax_oeffnet_erledigten_schritt(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('manage procedures');

        ['laufend' => $laufend, 'step' => $step] = $this->erstelleGestarteten($user);
        $step->update(['done' => true, 'completed_at' => now()]);

        $response = $this->postJson("/procedure/steps/{$step->id}/reopen");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Schritt wieder geöffnet.']);

        $this->assertDatabaseHas('procedure_steps', [
            'id'   => $step->id,
            'done' => false,
        ]);
    }

    public function test_step_reopen_ajax_ohne_manage_gibt_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('view assigned procedures');

        ['step' => $step] = $this->erstelleGestarteten($user);
        $step->update(['done' => true, 'completed_at' => now()]);

        $response = $this->postJson("/procedure/steps/{$step->id}/reopen");

        $response->assertStatus(403);
    }

    // ─── Schritt-Reihenfolge (B-09) ──────────────────────────────────────────

    public function test_reorder_steps_speichert_sort_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('manage procedures');

        $laufend = Procedure::factory()->gestartet()->create(['author_id' => $user->id]);
        $position = Positions::factory()->create();

        $step1 = Procedure_Step::factory()->create([
            'procedure_id' => $laufend->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 0,
        ]);
        $step2 = Procedure_Step::factory()->create([
            'procedure_id' => $laufend->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 1,
        ]);
        $step3 = Procedure_Step::factory()->create([
            'procedure_id' => $laufend->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 2,
        ]);

        // Neue Reihenfolge: 3, 1, 2
        $response = $this->postJson('/procedure/steps/reorder', [
            'procedure_id' => $laufend->id,
            'parent_id'    => null,
            'ordered_ids'  => [$step3->id, $step1->id, $step2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Reihenfolge gespeichert.']);

        $this->assertDatabaseHas('procedure_steps', ['id' => $step3->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('procedure_steps', ['id' => $step1->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('procedure_steps', ['id' => $step2->id, 'sort_order' => 2]);
    }

    public function test_reorder_ohne_manage_gibt_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('view assigned procedures');

        $laufend = Procedure::factory()->gestartet()->create();

        $response = $this->postJson('/procedure/steps/reorder', [
            'procedure_id' => $laufend->id,
            'parent_id'    => null,
            'ordered_ids'  => [],
        ]);

        $response->assertStatus(403);
    }

    public function test_move_step_updated_parent_and_sort_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('manage procedures');

        $laufend  = Procedure::factory()->gestartet()->create(['author_id' => $user->id]);
        $position = Positions::factory()->create();

        $parent = Procedure_Step::factory()->create([
            'procedure_id' => $laufend->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 0,
        ]);
        $child = Procedure_Step::factory()->create([
            'procedure_id' => $laufend->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 1,
        ]);

        $response = $this->patchJson("/procedure/steps/{$child->id}/move", [
            'parent_id'  => $parent->id,
            'sort_order' => 0,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('procedure_steps', [
            'id'         => $child->id,
            'parent'     => $parent->id,
            'sort_order' => 0,
        ]);
    }

    // ─── Vorlage duplizieren (B-05) ──────────────────────────────────────────

    public function test_vorlage_klonen_erstellt_kopie(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('manage procedures');

        $kategorie = Procedure_Category::factory()->create();
        $position  = Positions::factory()->create();

        $vorlage = Procedure::factory()->create([
            'name'        => 'Original',
            'category_id' => $kategorie->id,
            'author_id'   => $user->id,
            'started_at'  => null,
        ]);

        // Schritte anlegen
        $step1 = Procedure_Step::factory()->create([
            'procedure_id' => $vorlage->id,
            'name'         => 'Schritt 1',
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 0,
        ]);
        $step2 = Procedure_Step::factory()->create([
            'procedure_id' => $vorlage->id,
            'name'         => 'Unterschritt',
            'position_id'  => $position->id,
            'parent'       => $step1->id,
            'sort_order'   => 0,
        ]);

        $response = $this->post("/procedure/templates/{$vorlage->id}/clone");

        $response->assertRedirect();

        // Eine Kopie mit " (Kopie)" im Namen muss existieren
        $this->assertDatabaseHas('procedures', [
            'name'       => 'Original (Kopie)',
            'started_at' => null,
        ]);

        // Schritte der Kopie müssen ebenfalls existieren
        $kopie = Procedure::where('name', 'Original (Kopie)')->first();
        $this->assertNotNull($kopie);
        $this->assertEquals(2, $kopie->steps()->count());
    }

    public function test_vorlage_klonen_ohne_berechtigung_gibt_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        // Keine Berechtigungen

        $vorlage = Procedure::factory()->create(['started_at' => null]);

        $response = $this->post("/procedure/templates/{$vorlage->id}/clone");

        $response->assertStatus(403);
    }

    public function test_klonen_einer_gestarteten_instanz_gibt_redirect_mit_fehler(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('manage procedures');

        $laufend = Procedure::factory()->gestartet()->create(['author_id' => $user->id]);

        $response = $this->post("/procedure/templates/{$laufend->id}/clone");

        $response->assertRedirect()
            ->assertSessionHas('type', 'danger');
    }
}

