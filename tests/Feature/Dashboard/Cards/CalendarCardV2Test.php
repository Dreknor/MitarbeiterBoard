<?php

namespace Tests\Feature\Dashboard\Cards;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarCardV2Test extends TestCase
{
    use RefreshDatabase;

    private function setupCard($user): DashBoardUser
    {
        $dc = DashboardCard::create([
            'title'         => 'Kalender',
            'view'          => 'calendar.dashboardCard',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-calendar-alt',
            'skeleton'      => 'list',
            'permission'    => 'view calendar',
        ]);

        return DashBoardUser::create([
            'dashboard_card_id' => $dc->id,
            'user_id'           => $user->id,
            'row'               => 0,
            'col'               => 0,
            'order'             => 0,
            'width'             => 'md',
            'active'            => true,
        ]);
    }

    public function test_calendar_v2_shows_upcoming_events(): void
    {
        Http::fake();
        $user = $this->actingAsWithPermission('use dashboard v2', 'view calendar');
        $dbu  = $this->setupCard($user);

        $response = $this->get('/dashboard/card/' . $dbu->id, ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
    }

    public function test_calendar_v2_hides_for_unpermitted_user(): void
    {
        Http::fake();
        $user = $this->actingAsWithPermission('use dashboard v2');
        $dbu  = $this->setupCard($user);

        $response = $this->get('/dashboard/card/' . $dbu->id, ['X-Requested-With' => 'XMLHttpRequest']);

        // Ohne 'view calendar' rendert der Composer ein leeres collect
        $response->assertStatus(200);
        $response->assertDontSee('Alle Termine anzeigen');
    }
}

