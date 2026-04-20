<?php

namespace Tests\Feature\Dashboard\Cards;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsCardV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_tickets_v2_shows_open_tickets(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');

        Ticket::create([
            'title'       => 'Test-Ticket',
            'description' => 'Inhalt',
            'status'      => 'open',
            'user_id'     => $user->id,
        ]);

        $dc = DashboardCard::create([
            'title'         => 'Tickets',
            'view'          => 'ticketsystem.dashboardCard',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-headset',
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
        $response->assertSee('Test-Ticket');
    }

    public function test_tickets_v2_shows_correct_status_badge(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');

        Ticket::create([
            'title'       => 'Warte-Ticket',
            'description' => 'Inhalt',
            'status'      => 'waiting',
            'user_id'     => $user->id,
        ]);

        $dc = DashboardCard::create([
            'title'         => 'Tickets',
            'view'          => 'ticketsystem.dashboardCard',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-headset',
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
        $response->assertSee('wartend');
    }
}
