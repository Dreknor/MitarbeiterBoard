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
        // Prüfen, ob der Index existiert, bevor wir versuchen ihn zu löschen
        $indexExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = 'diagnostic_sessions'
             AND index_name = 'unique_open_session'"
        );

        if ($indexExists[0]->count > 0) {
            Schema::table('diagnostic_sessions', function (Blueprint $table) {
                // Alten falschen Index entfernen
                $table->dropUnique('unique_open_session');
            });
        }

        // Optional: Normaler Index zur Performance-Optimierung
        // Prüfen, ob der Index bereits existiert
        $newIndexExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = 'diagnostic_sessions'
             AND index_name = 'idx_session_lookup'"
        );

        if ($newIndexExists[0]->count == 0) {
            Schema::table('diagnostic_sessions', function (Blueprint $table) {
                $table->index(['schueler_id', 'diagnostic_area_id', 'is_completed'], 'idx_session_lookup');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Prüfen, ob der Index existiert, bevor wir versuchen ihn zu löschen
        $indexExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = 'diagnostic_sessions'
             AND index_name = 'idx_session_lookup'"
        );

        if ($indexExists[0]->count > 0) {
            Schema::table('diagnostic_sessions', function (Blueprint $table) {
                $table->dropIndex('idx_session_lookup');
            });
        }

        // Unique index wieder herstellen, wenn er nicht existiert
        $uniqueExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = 'diagnostic_sessions'
             AND index_name = 'unique_open_session'"
        );

        if ($uniqueExists[0]->count == 0) {
            Schema::table('diagnostic_sessions', function (Blueprint $table) {
                $table->unique(['schueler_id', 'diagnostic_area_id', 'is_completed'], 'unique_open_session');
            });
        }
    }
};

