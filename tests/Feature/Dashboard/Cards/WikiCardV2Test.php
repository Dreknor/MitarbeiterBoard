<?php

namespace Tests\Feature\Dashboard\Cards;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use App\Models\User;
use App\Models\WikiSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WikiCardV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_wiki_v2_shows_recent_pages(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');

        WikiSite::create([
            'title'     => 'Test-Wiki-Seite',
            'text'      => 'Inhalt',
            'author_id' => $user->id,
        ]);

        $dc = DashboardCard::create([
            'title'         => 'Wiki',
            'view'          => 'wiki.dashboardCard',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-book',
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
        $response->assertSee('Test-Wiki-Seite');
    }
}

