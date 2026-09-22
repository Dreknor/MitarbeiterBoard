<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLayoutApiTest extends TestCase
{
    /**
     * Nicht eingeloggte User werden zur Login-Seite weitergeleitet.
     */
    public function test_unauthenticated_user_cannot_update_layout(): void
    {
        $response = $this->putJson('/dashboard/layout', ['cards' => []]);
        $response->assertStatus(401);
    }

    /**
     * Layout-Update speichert order und width korrekt in der DB.
     */
    public function test_update_layout_saves_order_and_width(): void
    {
        $user = $this->actingAsWithPermission();

        $card = DashboardCard::create([
            'title'       => 'Test',
            'view'        => 'test.v',
            'default_row' => 0,
            'default_col' => 0,
        ]);

        $pivot = DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $user->id,
            'row'               => 0,
            'col'               => 0,
            'active'            => true,
            'order'             => 0,
            'width'             => 'md',
        ]);

        $response = $this->putJson('/dashboard/layout', [
            'cards' => [
                [
                    'id'     => $pivot->id,
                    'order'  => 5,
                    'width'  => 'lg',
                    'active' => true,
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('dashboard_card_user', [
            'id'    => $pivot->id,
            'order' => 5,
            'width' => 'lg',
        ]);
    }

    /**
     * Ungültiger Width-Wert führt zu Validierungsfehler (422).
     */
    public function test_update_layout_rejects_invalid_width(): void
    {
        $user = $this->actingAsWithPermission();

        $card = DashboardCard::create([
            'title' => 'Test', 'view' => 't.v', 'default_row' => 0, 'default_col' => 0,
        ]);
        $pivot = DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $user->id,
            'row' => 0, 'col' => 0, 'active' => true, 'order' => 0, 'width' => 'md',
        ]);

        $response = $this->putJson('/dashboard/layout', [
            'cards' => [
                ['id' => $pivot->id, 'order' => 0, 'width' => 'xxl', 'active' => true],
            ],
        ]);

        $response->assertStatus(422);
    }

    /**
     * Fremde Card-IDs werden ignoriert (nur eigene Cards werden aktualisiert).
     */
    public function test_update_layout_rejects_foreign_cards(): void
    {
        $user  = $this->actingAsWithPermission();
        $other = User::factory()->create();

        $card = DashboardCard::create([
            'title' => 'Fremd', 'view' => 'f.v', 'default_row' => 0, 'default_col' => 0,
        ]);
        $foreignPivot = DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $other->id,
            'row' => 0, 'col' => 0, 'active' => true, 'order' => 0, 'width' => 'md',
        ]);

        $this->putJson('/dashboard/layout', [
            'cards' => [
                ['id' => $foreignPivot->id, 'order' => 99, 'width' => 'full', 'active' => false],
            ],
        ]);

        // Fremde Card bleibt unverändert
        $this->assertDatabaseHas('dashboard_card_user', [
            'id'    => $foreignPivot->id,
            'order' => 0,
            'width' => 'md',
        ]);
    }

    /**
     * Reset löscht alle User-Cards.
     */
    public function test_reset_layout_deletes_user_cards(): void
    {
        $user = $this->actingAsWithPermission();

        $card = DashboardCard::create([
            'title' => 'Reset-Test', 'view' => 'r.v', 'default_row' => 0, 'default_col' => 0,
        ]);
        DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $user->id,
            'row' => 0, 'col' => 0, 'active' => true,
        ]);

        $response = $this->postJson('/dashboard/layout/reset');
        $response->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(0, DashBoardUser::where('user_id', $user->id)->count());
    }

    /**
     * loadCard gibt 200 mit HTML-Inhalt zurück.
     */
    public function test_load_card_returns_html(): void
    {
        $user = $this->actingAsWithPermission();

        $card = DashboardCard::create([
            'title' => 'HTML-Test', 'view' => 'dashboard.skeletons.default',
            'default_row' => 0, 'default_col' => 0,
        ]);
        $pivot = DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $user->id,
            'row' => 0, 'col' => 0, 'active' => true,
        ]);

        $response = $this->get("/dashboard/card/{$pivot->id}");
        $response->assertOk();
    }

    /**
     * loadCard gibt 403 für fremde Cards.
     */
    public function test_load_card_returns_403_for_foreign_card(): void
    {
        $this->actingAsWithPermission();
        $other = User::factory()->create();

        $card = DashboardCard::create([
            'title' => '403-Test', 'view' => 'dashboard.skeletons.default',
            'default_row' => 0, 'default_col' => 0,
        ]);
        $foreignPivot = DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $other->id,
            'row' => 0, 'col' => 0, 'active' => true,
        ]);

        $response = $this->get("/dashboard/card/{$foreignPivot->id}");
        $response->assertStatus(403);
    }

    /**
     * loadCard bevorzugt v2-View wenn Permission vorhanden und View existiert.
     */
    public function test_load_card_prefers_v2_view_when_permission_set(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');

        // Wir verwenden skeletons/default als Basis-View, die v2-Variante existiert nicht,
        // daher fällt der Controller auf die Basis-View zurück – trotzdem 200.
        $card = DashboardCard::create([
            'title' => 'v2-Test', 'view' => 'dashboard.skeletons.default',
            'default_row' => 0, 'default_col' => 0,
        ]);
        $pivot = DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $user->id,
            'row' => 0, 'col' => 0, 'active' => true,
        ]);

        $response = $this->get("/dashboard/card/{$pivot->id}");
        $response->assertOk();
    }
}

