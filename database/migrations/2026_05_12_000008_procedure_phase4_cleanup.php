<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 – Abschluss-Migration:
 *  - Dashboard-Card-Referenz `procedure.dashboardCard` → `procedure.dashboardCard-v2`
 *    (die alte View-Datei existiert nicht mehr).
 *  - Veraltete Permission `view procedures` wurde bereits in
 *    2026_05_12_000001_remove_view_procedures_permission entfernt.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dashboard-Card View-Referenz auf v2 aktualisieren
        DB::table('dashboard_cards')
            ->where('view', 'procedure.dashboardCard')
            ->update(['view' => 'procedure.dashboardCard-v2']);
    }

    public function down(): void
    {
        DB::table('dashboard_cards')
            ->where('view', 'procedure.dashboardCard-v2')
            ->update(['view' => 'procedure.dashboardCard']);
    }
};

