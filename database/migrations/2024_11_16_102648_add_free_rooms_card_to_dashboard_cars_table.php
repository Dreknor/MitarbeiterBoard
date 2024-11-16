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
            'title' => 'Freie Räume',
            'default_row' => 3,
            'default_col' => 2,
            'view' => 'rooms.rooms.freeRoomsCard',
            'permission' => 'view roomBooking',
        ]);
        $card->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
