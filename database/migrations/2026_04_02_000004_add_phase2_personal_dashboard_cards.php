<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // P2-01: Ablaufende Dokumente
        if (! \App\Models\DashboardCard::where('view', 'personal.documents._expiring_documents_card')->exists()) {
            \App\Models\DashboardCard::create([
                'title'       => 'Ablaufende Dokumente',
                'view'        => 'personal.documents._expiring_documents_card',
                'permission'  => 'manage personal_documents',
                'default_row' => 3,
                'default_col' => 1,
            ]);
        }

        // P2-02: Nextcloud Sync-Fehler
        if (! \App\Models\DashboardCard::where('view', 'personal.documents._nc_sync_fehler_card')->exists()) {
            \App\Models\DashboardCard::create([
                'title'       => 'Nextcloud Sync-Fehler',
                'view'        => 'personal.documents._nc_sync_fehler_card',
                'permission'  => 'manage personal_documents',
                'default_row' => 3,
                'default_col' => 1,
            ]);
        }

        // P2-03: Fehlende/ablaufende Qualifikationen
        if (! \App\Models\DashboardCard::where('view', 'personal.qualifications._missing_qualifications_card')->exists()) {
            \App\Models\DashboardCard::create([
                'title'       => 'Qualifikationen',
                'view'        => 'personal.qualifications._missing_qualifications_card',
                'permission'  => 'manage qualifications',
                'default_row' => 3,
                'default_col' => 2,
            ]);
        }

        // P2-04: Anstehende Fortbildungen
        if (! \App\Models\DashboardCard::where('view', 'personal.trainings._upcoming_trainings_card')->exists()) {
            \App\Models\DashboardCard::create([
                'title'       => 'Anstehende Fortbildungen',
                'view'        => 'personal.trainings._upcoming_trainings_card',
                'permission'  => 'view trainings',
                'default_row' => 3,
                'default_col' => 3,
            ]);
        }
    }

    public function down(): void
    {
        \App\Models\DashboardCard::whereIn('view', [
            'personal.documents._expiring_documents_card',
            'personal.documents._nc_sync_fehler_card',
            'personal.qualifications._missing_qualifications_card',
            'personal.trainings._upcoming_trainings_card',
        ])->delete();
    }
};

