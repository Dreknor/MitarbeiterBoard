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
            'title' => 'Arbeitszeiterfassung (eigene)',
            'view' => 'personal.time_recording.dashboardCardOwn',
            'default_row' => 3,
            'default_col' => 3,
            'permission' => 'has timesheet',
        ]);
        $card->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard_cards', function (Blueprint $table) {
            \App\Models\DashboardCard::where('title', 'Arbeitszeiterfassung (eigene)')->delete();
        });

    }
};
