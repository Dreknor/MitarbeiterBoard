<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Prüfen, ob die Tabelle überhaupt existiert
        if (!Schema::hasTable('diagnostic_sessions')) {
            return;
        }

        try {
            // Prüfen, ob der Index existiert, bevor wir versuchen ihn zu löschen
            $indexExists = DB::select(
                "SELECT COUNT(*) as count FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                 AND table_name = 'diagnostic_sessions'
                 AND index_name = 'unique_open_session'"
            );

            if ($indexExists && isset($indexExists[0]) && $indexExists[0]->count > 0) {
                try {
                    // Versuche den Index über SQL zu löschen, um FK-Probleme zu vermeiden
                    DB::statement('ALTER TABLE diagnostic_sessions DROP INDEX unique_open_session');
                } catch (\Exception $e) {
                    // Wenn es nicht funktioniert, loggen wir es nur
                    Log::warning('Could not drop unique_open_session index: ' . $e->getMessage());
                }
            }

            // Optional: Normaler Index zur Performance-Optimierung
            // Prüfen, ob der Index bereits existiert
            $newIndexExists = DB::select(
                "SELECT COUNT(*) as count FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                 AND table_name = 'diagnostic_sessions'
                 AND index_name = 'idx_session_lookup'"
            );

            if ($newIndexExists && isset($newIndexExists[0]) && $newIndexExists[0]->count == 0) {
                try {
                    Schema::table('diagnostic_sessions', function (Blueprint $table) {
                        $table->index(['schueler_id', 'diagnostic_area_id', 'is_completed'], 'idx_session_lookup');
                    });
                } catch (\Exception $e) {
                    // Wenn es nicht funktioniert, loggen wir es nur
                    Log::warning('Could not create idx_session_lookup index: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            // Falls irgendwas schief geht, loggen wir es nur und fahren fort
            Log::warning('Error in fix_unique_open_session_constraint migration: ' . $e->getMessage());
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

