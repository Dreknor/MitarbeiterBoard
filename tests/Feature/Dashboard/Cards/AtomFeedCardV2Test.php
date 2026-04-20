<?php

namespace Tests\Feature\Dashboard\Cards;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

class AtomFeedCardV2Test extends TestCase
{
    use RefreshDatabase, MocksExternalApis;

    public function test_atom_feed_v2_renders_entries(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $user = $this->actingAsWithPermission('use dashboard v2');

        $dc = DashboardCard::create([
            'title'         => 'Atom-Feed',
            'view'          => 'atom-feed.dashboardCard',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-rss',
            'skeleton'      => 'list',
            'permission'    => null,
        ]);

        $dbu = DashBoardUser::create([
            'dashboard_card_id' => $dc->id,
            'user_id'           => $user->id,
            'row'               => 0,
            'col'               => 0,
            'order'             => 0,
            'width'             => 'md',
            'active'            => true,
        ]);

        $response = $this->get('/dashboard/card/' . $dbu->id, ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
    }
}
