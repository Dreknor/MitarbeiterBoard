<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. pers_salary_tables (Referenztabelle für Gehaltstabellen)
        Schema::create('pers_salary_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_reference')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->json('data');           // Gruppen × Stufen mit Werten
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. pers_school_types (Schularten für Lehrer-Deputat)
        Schema::create('pers_school_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('default_deputat', 5, 2);
            $table->json('stundentafel')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed-Daten für Standard-Schularten
        DB::table('pers_school_types')->insert([
            [
                'name'            => 'Grundschule',
                'default_deputat' => 28.00,
                'stundentafel'    => json_encode(['klasse1_4' => ['deutsch' => 5, 'mathe' => 5, 'sachkunde' => 3]]),
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'name'            => 'Oberschule',
                'default_deputat' => 26.00,
                'stundentafel'    => json_encode(['klasse5_10' => ['deutsch' => 4, 'mathe' => 4, 'englisch' => 3]]),
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);

        // 3. pers_teacher_details (Lehrer-spezifische Anstellungsdetails)
        Schema::create('pers_teacher_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->cascadeOnDelete();
            $table->foreignId('school_type_id')->constrained('pers_school_types');
            $table->decimal('deputat_hours', 5, 2);
            $table->decimal('reduction_hours', 5, 2)->default(0);
            $table->string('reduction_reason')->nullable();
            $table->decimal('anrechnungsstunden', 5, 2)->default(0);
            // effective_hours wird als Accessor berechnet, nicht gespeichert
            $table->date('valid_from');
            $table->date('valid_until')->nullable(); // null = aktuell gültig
            $table->timestamps();
        });

        // 4. pers_teacher_subjects (Unterrichtsfächer pro TeacherDetail)
        Schema::create('pers_teacher_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_detail_id')->constrained('pers_teacher_details')->cascadeOnDelete();
            $table->string('subject');              // Freitext (Autovervollständigung)
            $table->string('qualification_level'); // PHP Enum TeacherQualificationLevel
            $table->decimal('hours_per_week', 4, 2)->nullable();
            $table->timestamps();
        });

        // 5. FK für salary_table_id in employments (war bisher ohne FK)
        Schema::table('employments', function (Blueprint $table) {
            // Spalte ggf. anlegen falls noch nicht vorhanden (PHP fillable, aber ggf. kein DB-Feld)
            if (!Schema::hasColumn('employments', 'salary_table_id')) {
                $table->foreignId('salary_table_id')->nullable()
                    ->after('salary_level')
                    ->constrained('pers_salary_tables')
                    ->nullOnDelete();
            } else {
                $table->foreign('salary_table_id')
                    ->references('id')->on('pers_salary_tables')
                    ->nullOnDelete();
            }
        });

        // 6. primary_department_id zu employes_data (manuelle Überschreibung für NC-Ordnerstruktur)
        Schema::table('employes_data', function (Blueprint $table) {
            $table->foreignId('primary_department_id')
                ->nullable()
                ->constrained('groups')
                ->nullOnDelete()
                ->after('geburtstag')
                ->comment('Manuelle Überschreibung der primären Gruppe für NC-Ordnerstruktur. Null = automatisch.');
        });
    }

    public function down(): void
    {
        Schema::table('employes_data', function (Blueprint $table) {
            $table->dropForeign(['primary_department_id']);
            $table->dropColumn('primary_department_id');
        });

        Schema::table('employments', function (Blueprint $table) {
            $table->dropForeign(['salary_table_id']);
        });

        Schema::dropIfExists('pers_teacher_subjects');
        Schema::dropIfExists('pers_teacher_details');
        Schema::dropIfExists('pers_school_types');
        Schema::dropIfExists('pers_salary_tables');
    }
};

