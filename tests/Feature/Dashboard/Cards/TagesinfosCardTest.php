<?php

namespace Tests\Feature\Dashboard\Cards;

use App\Models\DailyNews;
use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagesinfosCardTest extends TestCase
{
    use RefreshDatabase;

    private function setupCard($user): DashBoardUser
    {
        $dc = DashboardCard::create([
            'title'         => 'Tagesinfos',
            'view'          => 'dashboard.cards.tagesinfos',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-bullhorn',
            'skeleton'      => 'list',
            'permission'    => null,
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

    public function test_tagesinfos_shows_current_news(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');
        $dbu  = $this->setupCard($user);

        DailyNews::create([
            'news'       => 'Aktuelle Tagesinfo',
            'date_start' => today(),
            'date_end'   => null,
        ]);

        $response = $this->get('/dashboard/card/' . $dbu->id, ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $response->assertSee('Aktuelle Tagesinfo');
    }

    public function test_tagesinfos_hides_expired_news(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');
        $dbu  = $this->setupCard($user);

        DailyNews::create([
            'news'       => 'AbgelaufeneInfo',
            'date_start' => today()->subDays(5),
            'date_end'   => today()->subDays(1),
        ]);

        $response = $this->get('/dashboard/card/' . $dbu->id, ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $response->assertDontSee('AbgelaufeneInfo');
    }
}

