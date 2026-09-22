<?php

namespace Tests\Feature\Dashboard\Cards;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeRoomsCardV2Test extends TestCase
{
    use RefreshDatabase;

    private function loadCardResponse($user): \Illuminate\Testing\TestResponse
    {
        $dc = DashboardCard::create([
            'title'         => 'Freie Räume',
            'view'          => 'rooms.rooms.freeRoomsCard',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-door-open',
            'skeleton'      => 'default',
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

        return $this->get('/dashboard/card/' . $dbu->id, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
    }

    public function test_free_rooms_v2_renders_rooms(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');

        $dc = DashboardCard::create([
            'title'         => 'Freie Räume',
            'view'          => 'rooms.rooms.freeRoomsCard',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-door-open',
            'skeleton'      => 'default',
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

        // v2-View laden
        $response = $this->get('/dashboard/card/' . $dbu->id, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);
    }
}

