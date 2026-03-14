<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('dashboard_cards')) {
            DB::table('dashboard_cards')->insertOrIgnore([
                'title'       => 'Kalender',
                'view'        => 'calendar.dashboardCard',
                'default_row' => 3,
                'default_col' => 1,
                'permission'  => 'view calendar',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('dashboard_cards')->where('view', 'calendar.dashboardCard')->delete();
    }
};
