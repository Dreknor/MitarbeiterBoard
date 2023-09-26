<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $card = new \App\Models\DashboardCard([
           'title' => 'Wiki',
            'view' => 'wiki.dashboardCard',
            'default_row' => 2,
            'default_col' => 3,
            'permission' => 'view wiki',
        ]);
        $card->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard_cards', function (Blueprint $table) {
            \App\Models\DashboardCard::where('title', 'Wiki')->delete();
        });
    }
};
