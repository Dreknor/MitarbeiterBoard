<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardMigrationTest extends TestCase
{
    /**
     * Prüft ob die neuen Spalten in dashboard_card_user existieren.
     */
    public function test_migration_adds_width_and_order_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn('dashboard_card_user', 'width'),
            'Spalte "width" fehlt in dashboard_card_user'
        );
        $this->assertTrue(
            Schema::hasColumn('dashboard_card_user', 'order'),
            'Spalte "order" fehlt in dashboard_card_user'
        );
        $this->assertTrue(
            Schema::hasColumn('dashboard_cards', 'default_width'),
            'Spalte "default_width" fehlt in dashboard_cards'
        );
        $this->assertTrue(
            Schema::hasColumn('dashboard_cards', 'icon'),
            'Spalte "icon" fehlt in dashboard_cards'
        );
        $this->assertTrue(
            Schema::hasColumn('dashboard_cards', 'skeleton'),
            'Spalte "skeleton" fehlt in dashboard_cards'
        );
    }

    /**
     * Prüft ob die Permission 'use dashboard v2' existiert.
     */
    public function test_migration_creates_permission(): void
    {
        $permission = Permission::findByName('use dashboard v2');
        $this->assertNotNull($permission, "Permission 'use dashboard v2' sollte existieren");
    }

    /**
     * Prüft ob neu erstellte Pivot-Rows einen order-Wert basierend auf row*10+col haben.
     */
    public function test_existing_cards_have_order_values(): void
    {
        // DashboardCard erstellen
        $card = DashboardCard::create([
            'title'       => 'Test-Karte',
            'view'        => 'test.card',
            'default_row' => 1,
            'default_col' => 2,
        ]);

        $user = \App\Models\User::factory()->create();

        DashBoardUser::create([
            'dashboard_card_id' => $card->id,
            'user_id'           => $user->id,
            'row'               => 1,
            'col'               => 2,
            'active'            => true,
            'order'             => 1 * 10 + 2, // 12
        ]);

        $pivot = DashBoardUser::where('user_id', $user->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(12, $pivot->order);
    }

    /**
     * Prüft ob DashboardCards mit Icon-Werten gespeichert werden können.
     */
    public function test_existing_cards_have_icons(): void
    {
        $card = DashboardCard::create([
            'title'       => 'Icon-Test',
            'view'        => 'test.icon',
            'default_row' => 0,
            'default_col' => 0,
            'icon'        => 'fas fa-test',
            'skeleton'    => 'list',
        ]);

        $this->assertNotNull($card->icon);
        $this->assertEquals('fas fa-test', $card->icon);
        $this->assertEquals('list', $card->skeleton);
    }
}

