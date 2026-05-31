<?php

namespace Tests\Feature\Procedure;

use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Step;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests für Positions-Verwaltung im Prozess-Modul.
 */
class ProcedurePositionTest extends TestCase
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_admin_sieht_positions_index(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->get('/procedure/positions');

        $response->assertStatus(200);
    }

    public function test_nutzer_ohne_manage_wird_abgewiesen(): void
    {
        $this->actingAsWithPermission('view assigned procedures');

        $response = $this->get('/procedure/positions');

        $response->assertStatus(403);
    }

    // ─── Position anlegen ────────────────────────────────────────────────────

    public function test_admin_kann_position_anlegen(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->post('/procedure/position', [
            'name' => 'Schulleitung',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('positions', ['name' => 'Schulleitung']);
    }

    public function test_position_anlegen_ohne_name_schlaegt_fehl(): void
    {
        $this->actingAsWithPermission('manage procedures');

        $response = $this->post('/procedure/position', ['name' => '']);

        $response->assertSessionHasErrors(['name']);
    }

    // ─── Person zuweisen ─────────────────────────────────────────────────────

    public function test_admin_kann_person_zu_position_zuweisen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $user     = User::factory()->create();

        $response = $this->post("/procedure/positions/{$position->id}/add", [
            'person_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('position_user', [
            'position_id' => $position->id,
            'user_id'     => $user->id,
        ]);
    }

    public function test_doppelte_zuweisung_zu_position_wird_ignoriert(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $user     = User::factory()->create();
        $position->users()->attach($user->id);

        // Erneut hinzufügen
        $this->post("/procedure/positions/{$position->id}/add", [
            'person_id' => $user->id,
        ]);

        $this->assertEquals(
            1,
            $position->users()->where('user_id', $user->id)->count()
        );
    }

    // ─── Person entfernen ────────────────────────────────────────────────────

    public function test_admin_kann_person_von_position_entfernen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $user     = User::factory()->create();
        $position->users()->attach($user->id);

        $response = $this->get("/procedure/positions/{$position->id}/remove/{$user->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('position_user', [
            'position_id' => $position->id,
            'user_id'     => $user->id,
        ]);
    }
}

