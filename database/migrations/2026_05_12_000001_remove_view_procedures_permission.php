<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Phase 4 – Cleanup: Entfernt die veraltete Permission `view procedures` aus der DB.
 * Benutzer wurden bereits in Migration 2026_01_22_133826 auf `manage procedures` migriert.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pivot-Einträge (user_has_permissions, role_has_permissions) kaskadieren per FK.
        \Illuminate\Support\Facades\DB::table('permissions')
            ->where('name', 'view procedures')
            ->where('guard_name', 'web')
            ->delete();

        \Illuminate\Support\Facades\Artisan::call('cache:clear');
    }

    public function down(): void
    {
        // Permission wiederherstellen (ohne Rollenzuweisungen)
        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name'       => 'view procedures',
            'guard_name' => 'web',
        ]);

        \Illuminate\Support\Facades\Artisan::call('cache:clear');
    }
};

