<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardV2RoutingTest extends TestCase
{
    /**
     * User mit Permission 'use dashboard v2' sieht den v2-View.
     */
    public function test_user_with_permission_sees_v2_dashboard(): void
    {
        $this->actingAsWithPermission('use dashboard v2');

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewIs('dashboard.dashboard-v2');
    }

    /**
     * User ohne Permission sieht das alte Dashboard.
     */
    public function test_user_without_permission_sees_old_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewIs('dashboard.dashboard');
    }

    /**
     * Beim ersten Besuch werden Default-Cards angelegt.
     */
    public function test_first_visit_creates_default_cards(): void
    {
        // Default-Card in der DB anlegen
        DashboardCard::create([
            'title'         => 'Test-Karte',
            'view'          => 'dashboard.skeletons.default',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'permission'    => null,
        ]);

        $user = $this->actingAsWithPermission('use dashboard v2');

        // Sicherstellen: keine Cards vorhanden
        $this->assertEquals(0, DashBoardUser::where('user_id', $user->id)->count());

        $this->get('/');

        // Nach dem ersten Besuch sollten Cards angelegt worden sein
        $this->assertGreaterThan(0, DashBoardUser::where('user_id', $user->id)->count());
    }
}

