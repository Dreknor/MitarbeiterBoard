<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fügt die Option "Offene Einträge bei Termin pausieren" hinzu.
     */
    public function up(): void
    {
        Schema::table('paed_diary_appointments', function (Blueprint $table) {
            $table->boolean('pause_entries')->default(false)->after('is_paused')
                ->comment('Offene Einträge für betroffene Schüler am Termintag automatisch pausieren');
        });
    }

    public function down(): void
    {
        Schema::table('paed_diary_appointments', function (Blueprint $table) {
            $table->dropColumn('pause_entries');
        });
    }
};

