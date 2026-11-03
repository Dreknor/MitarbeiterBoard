<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personalverwaltung: Verknüpfung Mitarbeiter ↔ Prozesse (On-/Offboarding) und Erinnerungen
 * (Probezeit, Vertragsende, Aufbewahrungsfristen) + zugehörige Einstellungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pers_procedure_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employment_id')->nullable()->constrained('employments')->nullOnDelete();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->string('type', 20);                       // ProcedureLinkType
            $table->string('status', 20)->default('aktiv');   // ProcedureLinkStatus
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['employe_id', 'type']);
            $table->unique('procedure_id');
        });

        Schema::create('pers_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employment_id')->nullable()->constrained('employments')->cascadeOnDelete();
            $table->string('type', 20);                       // probation | contract_end | retention
            $table->date('due_date');
            $table->unsignedSmallInteger('lead_days')->default(14); // so viele Tage vorher erinnern
            $table->string('note')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['due_date', 'done_at']);
            $table->index(['employe_id', 'type']);
        });

        // Fehlende Pflicht-Qualifikationen werden ohne Erwerbsdatum angelegt (Status "fehlend")
        Schema::table('pers_employee_qualifications', function (Blueprint $table) {
            $table->date('acquired_date')->nullable()->change();
        });

        // Recht "Änderungsverlauf der Personalakte": Rollen mit Vertragsbearbeitung erhalten es zunächst automatisch
        $perm = \Spatie\Permission\Models\Permission::findOrCreate('view personal_audit', 'web');
        $source = \Spatie\Permission\Models\Permission::where('name', 'edit contracts')->where('guard_name', 'web')->first();
        foreach ($source?->roles ?? [] as $role) {
            $role->givePermissionTo($perm);
        }

        $now = now();
        $settings = [
            ['onboarding_template_id', 'Onboarding-Vorlage (Prozess-ID)', 'number', '', 'ID der Prozess-Vorlage, die bei der ersten Anstellung einer Person automatisch gestartet wird. Leer = kein Onboarding.'],
            ['offboarding_template_id', 'Offboarding-Vorlage (Prozess-ID)', 'number', '', 'ID der Prozess-Vorlage, die beim Ausscheiden (keine weitere laufende Anstellung) automatisch gestartet wird. Leer = kein Offboarding.'],
            ['probezeit_erinnerung_tage', 'Probezeit: Erinnerung vorab (Tage)', 'number', '14', 'So viele Tage vor Ende der Probezeit wird die Personalverwaltung erinnert.'],
            ['vertragsende_erinnerung_tage', 'Vertragsende: Erinnerung vorab (Tage)', 'number', '60', 'So viele Tage vor Ablauf eines befristeten Vertrags wird die Personalverwaltung erinnert.'],
            ['aufbewahrung_jahre', 'Aufbewahrungsfrist nach Ausscheiden (Jahre)', 'number', '10', 'Nach dieser Frist wird zur Prüfung/Löschung der Personalakte erinnert (DSGVO).'],
        ];
        foreach ($settings as [$key, $name, $type, $value, $desc]) {
            DB::table('settings')->updateOrInsert(
                ['setting' => $key],
                ['module' => 'Personal', 'setting_name' => $name, 'type' => $type, 'value' => $value,
                 'description' => $desc, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pers_reminders');
        Schema::dropIfExists('pers_procedure_links');
        DB::table('settings')->where('module', 'Personal')->whereIn('setting', [
            'onboarding_template_id', 'offboarding_template_id', 'probezeit_erinnerung_tage',
            'vertragsende_erinnerung_tage', 'aufbewahrung_jahre',
        ])->delete();
    }
};
