<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Phase 0 – P0-04: Neue Personalverwaltungs-Felder zur employments-Tabelle hinzufügen.
 *
 * Migrationsstrategie:
 * 1. Neue Spalten nullable hinzufügen
 * 2. Default-Werte für bestehende Datensätze setzen
 * 3. Permissions registrieren
 *
 * Bestehende salary-Felder (salary_type, salary) bleiben erhalten.
 * Erst nach manuellem Daten-Mapping werden sie in einer späteren Migration entfernt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            // Neue Typ-Felder (nullable für schrittweise Migration)
            $table->string('employment_type')->nullable()->after('comment');
            $table->string('contract_type')->nullable()->after('employment_type');
            $table->string('status')->default('aktiv')->after('contract_type');
            $table->string('status_reason')->nullable()->after('status');
            $table->string('termination_reason')->nullable()->after('status_reason');

            // Neue Vertrags-Detailfelder
            $table->date('probation_end')->nullable()->after('end');
            $table->string('notice_period')->nullable()->after('probation_end');

            // Neue Gehalt-Felder (ergänzt bestehende salary-Felder)
            $table->string('salary_group')->nullable()->after('hours');
            $table->string('salary_level')->nullable()->after('salary_group');
            // salary_table_id als nullable Column – FK wird in späterer Migration gesetzt wenn pers_salary_tables existiert
            $table->unsignedBigInteger('salary_table_id')->nullable()->after('salary_level');

            // Nachtrag / Versetzung
            $table->boolean('is_amendment')->default(false)->after('replaced_employment_id');
            $table->string('amendment_description')->nullable()->after('is_amendment');
            $table->boolean('is_internal_transfer')->default(false)->after('amendment_description');
        });

        // Default-Werte für bestehende Datensätze setzen
        \DB::table('employments')->whereNull('employment_type')
            ->update(['employment_type' => 'regulaer']);

        \DB::table('employments')->whereNull('contract_type')->whereNull('end')
            ->update(['contract_type' => 'unbefristet']);

        \DB::table('employments')->whereNull('contract_type')->whereNotNull('end')
            ->update(['contract_type' => 'befristet']);

        // Permissions registrieren
        $permissions = [
            'view contracts',
            'edit contracts',
            'view salary',
            'edit salary',
        ];
        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
    }

    public function down(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->dropColumn([
                'employment_type',
                'contract_type',
                'status',
                'status_reason',
                'termination_reason',
                'probation_end',
                'notice_period',
                'salary_group',
                'salary_level',
                'salary_table_id',
                'is_amendment',
                'amendment_description',
                'is_internal_transfer',
            ]);
        });
    }
};

