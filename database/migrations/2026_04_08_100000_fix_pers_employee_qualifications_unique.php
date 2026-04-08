<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        // Unique-Constraint mit kurzem Namen hinzufügen (MySQL-Limit: 64 Zeichen)
        // Der ursprüngliche Name war zu lang:
        // pers_employee_qualifications_employe_id_qualification_type_id_unique (69 Zeichen)
        Schema::table('pers_employee_qualifications', function (Blueprint $table) {
            // Prüfen ob der Constraint bereits existiert
            $indexes = collect(DB::select("SHOW INDEX FROM pers_employee_qualifications WHERE Key_name = 'pers_emp_qual_emp_qualtype_unique'"));
            if ($indexes->isEmpty()) {
                $table->unique(['employe_id', 'qualification_type_id'], 'pers_emp_qual_emp_qualtype_unique');
            }
        });
    }
    public function down(): void
    {
        Schema::table('pers_employee_qualifications', function (Blueprint $table) {
            $table->dropUnique('pers_emp_qual_emp_qualtype_unique');
        });
    }
};
