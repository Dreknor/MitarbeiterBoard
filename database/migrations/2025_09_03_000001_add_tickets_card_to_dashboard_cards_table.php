<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Karte nur anlegen wenn noch nicht vorhanden
        if (! \App\Models\DashboardCard::where('view', 'ticketsystem.dashboardCard')->exists()) {
            \App\Models\DashboardCard::create([
                'title' => 'Tickets',
                'view' => 'ticketsystem.dashboardCard',
                'default_row' => 2,
                'default_col' => 4,
                'permission' => 'view tickets',
            ]);
        }
    }

    public function down(): void
    {
        \App\Models\DashboardCard::where('view', 'ticketsystem.dashboardCard')->delete();
    }
};

