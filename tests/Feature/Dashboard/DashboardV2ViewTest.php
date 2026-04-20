<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

class DashboardV2ViewTest extends TestCase
{
    use RefreshDatabase, MocksExternalApis;

    private function createCardForUser($user, array $cardAttributes = []): DashBoardUser
    {
        $card = DashboardCard::create(array_merge([
            'title'         => 'Test-Karte',
            'view'          => 'dashboard.skeletons.default',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-th',
            'skeleton'      => 'default',
            'permission'    => null,
        ], $cardAttributes));

        return DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $user->id,
            'row'               => 0,
            'col'               => 0,
            'order'             => 0,
            'width'             => 'md',
            'active'            => true,
        ]);
    }

    public function test_dashboard_v2_renders_for_permitted_user(): void
    {
        $this->fakeAllExternalApis();
        $user = $this->actingAsWithPermission('use dashboard v2');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSeeText('Guten');
    }

    public function test_dashboard_v2_shows_all_active_cards(): void
    {
        $this->fakeAllExternalApis();
        $user = $this->actingAsWithPermission('use dashboard v2');
        $cardUser = $this->createCardForUser($user);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('data-card-id="' . $cardUser->id . '"', false);
    }

    public function test_dashboard_v2_hides_inactive_cards(): void
    {
        $this->fakeAllExternalApis();
        $user = $this->actingAsWithPermission('use dashboard v2');
        $cardUser = $this->createCardForUser($user, ['title' => 'InaktiveKarte_' . uniqid()]);
        $cardUser->update(['active' => false]);

        $response = $this->get('/');

        // Inaktive Card ist nicht direkt im foreach (nur aktive werden gerendert)
        $response->assertStatus(200);
        $response->assertDontSee('data-card-id="' . $cardUser->id . '"', false);
    }

    public function test_dashboard_v2_loads_vite_assets(): void
    {
        $this->fakeAllExternalApis();
        $this->actingAsWithPermission('use dashboard v2');

        $response = $this->get('/');

        $response->assertStatus(200);
        // Dashboard-JS/CSS sollte geladen werden
        $response->assertSee('dashboard', false);
    }
}

