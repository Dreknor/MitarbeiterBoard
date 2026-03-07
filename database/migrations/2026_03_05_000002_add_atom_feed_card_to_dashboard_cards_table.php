<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!\App\Models\DashboardCard::where('view', 'atom-feed.dashboardCard')->exists()) {
            \App\Models\DashboardCard::create([
                'title'       => 'Veranstaltungen (Feed)',
                'view'        => 'atom-feed.dashboardCard',
                'default_row' => 2,
                'default_col' => 3,
                'permission'  => null,
            ]);
        }
    }

    public function down(): void
    {
        \App\Models\DashboardCard::where('view', 'atom-feed.dashboardCard')->delete();
    }
};

