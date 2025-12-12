<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('diagnostic_sessions', function (Blueprint $table) {
            // Alten falschen Index entfernen
            $table->dropUnique('unique_open_session');
        });


        // Optional: Normaler Index zur Performance-Optimierung
        Schema::table('diagnostic_sessions', function (Blueprint $table) {
            $table->index(['schueler_id', 'diagnostic_area_id', 'is_completed'], 'idx_session_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnostic_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_session_lookup');
            $table->unique(['schueler_id', 'diagnostic_area_id', 'is_completed'], 'unique_open_session');
        });
    }
};

