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
        // Füge Setting für maximale Wiederöffnungsfrist hinzu
        DB::table('settings')->insert([
            'module' => 'grading_documentation',
            'setting' => 'session_reopen_days',
            'setting_name' => 'Wiederöffnungsfrist für abgeschlossene Sessions (Tage)',
            'type' => 'number',
            'value' => '30',
            'description' => 'Anzahl der Tage, innerhalb derer eine abgeschlossene Graduierungssystem-Dokumentationssession wiedergeöffnet werden kann. Standard: 30 Tage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('module', 'grading_documentation')
            ->where('setting', 'session_reopen_days')
            ->delete();
    }
};
