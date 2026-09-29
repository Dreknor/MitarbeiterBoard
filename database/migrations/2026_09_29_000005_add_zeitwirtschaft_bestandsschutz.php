<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitwirtschaft: Bestandsschutz für Altdaten.
 *
 * Idempotent – ergänzt nur, was fehlt (auch für Installationen, auf denen eine frühere
 * Fassung der Migrationen 2026_09_29_00000x bereits gelaufen ist).
 *  - Stichtag: Monate davor werden unverändert nach der bisherigen Methode berechnet.
 *  - Automatische Dienstplan-Übernahme (bisheriges Verhalten) inkl. Merker je Nachweis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('timesheets', 'plan_uebernommen_bis')) {
            Schema::table('timesheets', function (Blueprint $table) {
                $table->date('plan_uebernommen_bis')->nullable();
            });
        }

        $settings = [
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'zeitwirtschaft_stichtag',
                'setting_name' => 'Stichtag neues Arbeitszeitmodell',
                'type' => 'date',
                'value' => now()->startOfMonth()->addMonth()->toDateString(),
                'description' => 'Arbeitszeitnachweise vor diesem Tag werden unverändert nach der bisherigen Methode berechnet (Bestandsschutz). Ab diesem Monat gelten Arbeitszeitmodell, automatische Urlaubs-/Abwesenheitsgutschriften und Urlaubskonto.',
            ],
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'timesheet_dienstplan_automatisch',
                'setting_name' => 'Dienstplan automatisch in den Nachweis übernehmen',
                'type' => 'boolean',
                'value' => '1',
                'description' => 'Vergangene Tage ohne Buchung werden einmalig mit den Dienstplanzeiten gefüllt (bisheriges Verhalten). Aus = nur auf Klick.',
            ],
        ];

        foreach ($settings as $setting) {
            if (!Setting::where('setting', $setting['setting'])->exists()) {
                Setting::create($setting);
            }
        }
    }

    public function down(): void
    {
        // Spalte gehört zu 2026_09_29_000003 (neue Fassung) – nur entfernen, wenn sie hier ergänzt wurde, ist nicht feststellbar.
        Setting::whereIn('setting', ['zeitwirtschaft_stichtag', 'timesheet_dienstplan_automatisch'])->delete();
    }
};
