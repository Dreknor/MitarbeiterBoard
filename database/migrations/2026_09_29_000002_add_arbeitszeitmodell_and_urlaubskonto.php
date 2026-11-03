<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitwirtschaft P1/P2: Arbeitszeitmodell am Vertrag, Urlaubs-Workflow und Urlaubskonto.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Arbeitstage je Vertrag (ISO-Wochentage 1=Mo … 7=So). NULL = Montag bis Freitag.
        Schema::table('employments', function (Blueprint $table) {
            $table->json('workdays')->nullable()->after('hours');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->decimal('days', 5, 1)->nullable()->change();
            $table->boolean('half_day')->default(false)->after('end_date');
            $table->string('comment', 255)->nullable()->after('half_day');
            $table->string('rejection_reason', 255)->nullable()->after('rejected');
            $table->timestamp('cancellation_requested_at')->nullable()->after('rejection_reason');
            $table->string('cancellation_reason', 255)->nullable()->after('cancellation_requested_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
        });

        // Abwesenheit ↔ Urlaub fest verknüpfen (statt Abgleich über Feldwerte)
        Schema::table('absences', function (Blueprint $table) {
            $table->foreignId('holiday_id')->nullable()->after('users_id')->constrained('holidays')->nullOnDelete();
        });

        // Manuelle Buchungen auf dem Urlaubskonto (Übertrag, Sonderurlaub, Korrektur)
        Schema::create('holiday_account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users');
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 5, 1);
            $table->string('type', 32)->default('korrektur'); // korrektur | sonderurlaub | uebertrag
            $table->string('reason', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employe_id', 'year']);
        });

        $settings = [
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'vollzeit_stunden',
                'setting_name' => 'Wochenstunden bei Vollzeit',
                'type' => 'number',
                'value' => '40',
                'description' => 'Grundlage der Soll-Arbeitszeit: Soll je Woche = Stellenanteil × Vollzeit-Stunden, verteilt auf die Arbeitstage des Vertrags.',
            ],
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'urlaub_verfall_datum',
                'setting_name' => 'Verfall des Resturlaubs (MM-TT)',
                'type' => 'string',
                // Standard aus: bestehende Resturlaube dürfen durch das Update nicht plötzlich verfallen
                'value' => '',
                'description' => 'Bis zu diesem Tag (z. B. 03-31) muss übertragener Resturlaub genommen sein, sonst verfällt er. Leer = kein Verfall.',
            ],
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'urlaubskonto_startjahr',
                'setting_name' => 'Startjahr des Urlaubskontos',
                'type' => 'number',
                'value' => (string) now()->year,
                'description' => 'Für dieses Jahr wird der Übertrag noch aus den bisherigen Arbeitszeitnachweisen übernommen, danach automatisch berechnet.',
            ],
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'urlaub_anteilig',
                'setting_name' => 'Standard-Urlaubsanspruch anteilig berechnen',
                'type' => 'boolean',
                // Standard aus: bisher galt der Standardanspruch ungekürzt
                'value' => '0',
                'description' => 'Standardanspruch anteilig nach Beschäftigungsmonaten und Arbeitstagen pro Woche berechnen (individuell hinterlegte Ansprüche bleiben unverändert).',
            ],
        ];

        $settings[] = [
            'module' => 'Urlaubsplaner',
            'setting' => 'zeitwirtschaft_stichtag',
            'setting_name' => 'Stichtag neues Arbeitszeitmodell',
            'type' => 'date',
            // Ab dem Folgemonat der Umstellung – der laufende und alle früheren Monate werden exakt wie bisher berechnet
            'value' => now()->startOfMonth()->addMonth()->toDateString(),
            'description' => 'Arbeitszeitnachweise vor diesem Tag werden unverändert nach der bisherigen Methode berechnet (Bestandsschutz). Ab diesem Monat gelten Arbeitszeitmodell, automatische Urlaubs-/Abwesenheitsgutschriften und Urlaubskonto.',
        ];
        $settings[] = [
            'module' => 'Urlaubsplaner',
            'setting' => 'timesheet_dienstplan_automatisch',
            'setting_name' => 'Dienstplan automatisch in den Nachweis übernehmen',
            'type' => 'boolean',
            'value' => '1',
            'description' => 'Vergangene Tage ohne Buchung werden einmalig mit den Dienstplanzeiten gefüllt (bisheriges Verhalten). Aus = nur auf Klick.',
        ];

        foreach ($settings as $setting) {
            if (!Setting::where('setting', $setting['setting'])->exists()) {
                Setting::create($setting);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_account_entries');

        Schema::table('absences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holiday_id');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['half_day', 'comment', 'rejection_reason', 'cancellation_requested_at', 'cancellation_reason']);
        });

        Schema::table('employments', function (Blueprint $table) {
            $table->dropColumn('workdays');
        });

        Setting::whereIn('setting', ['vollzeit_stunden', 'urlaub_verfall_datum', 'urlaubskonto_startjahr', 'urlaub_anteilig', 'zeitwirtschaft_stichtag', 'timesheet_dienstplan_automatisch'])->delete();
    }
};
