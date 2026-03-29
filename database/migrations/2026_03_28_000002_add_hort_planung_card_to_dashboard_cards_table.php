<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! \App\Models\DashboardCard::where('view', 'personal.hort_planung.dashboardCard')->exists()) {
            \App\Models\DashboardCard::create([
                'title'       => 'Hortstunden-Planung',
                'view'        => 'personal.hort_planung.dashboardCard',
                'default_row' => 3,
                'default_col' => 1,
                'permission'  => 'view hort planung',
            ]);
        }
    }

    public function down(): void
    {
        \App\Models\DashboardCard::where('view', 'personal.hort_planung.dashboardCard')->delete();
    }
};

