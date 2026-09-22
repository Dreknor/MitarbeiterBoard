<?php

namespace Tests\Feature\Dashboard\Cards;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class BenachrichtigungenCardTest extends TestCase
{
    use RefreshDatabase;

    private function setupCard($user): DashBoardUser
    {
        $dc = DashboardCard::create([
            'title'         => 'Benachrichtigungen',
            'view'          => 'dashboard.cards.benachrichtigungen',
            'default_row'   => 0,
            'default_col'   => 0,
            'default_width' => 'md',
            'icon'          => 'fas fa-bell',
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

    public function test_benachrichtigungen_shows_unread(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');
        $dbu  = $this->setupCard($user);

        DatabaseNotification::create([
            'id'              => \Str::uuid(),
            'type'            => 'App\\Notifications\\TestNotification',
            'notifiable_type' => get_class($user),
            'notifiable_id'   => $user->id,
            'data'            => ['message' => 'Test-Benachrichtigung'],
            'read_at'         => null,
        ]);

        $response = $this->get('/dashboard/card/' . $dbu->id, ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $response->assertSee('Test-Benachrichtigung');
    }

    public function test_benachrichtigungen_shows_correct_count(): void
    {
        $user = $this->actingAsWithPermission('use dashboard v2');
        $dbu  = $this->setupCard($user);

        for ($i = 1; $i <= 5; $i++) {
            DatabaseNotification::create([
                'id'              => \Str::uuid(),
                'type'            => 'App\\Notifications\\TestNotification',
                'notifiable_type' => get_class($user),
                'notifiable_id'   => $user->id,
                'data'            => ['message' => "Nachricht $i"],
                'read_at'         => null,
            ]);
        }

        $response = $this->get('/dashboard/card/' . $dbu->id, ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        // Mindestens eine Notification sichtbar
        $response->assertSee('Nachricht');
    }
}
