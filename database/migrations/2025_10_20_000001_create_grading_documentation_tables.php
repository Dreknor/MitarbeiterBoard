<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Erstellt Tabellen für die Graduierungssystem-Dokumentation
     */
    public function up(): void
    {
        // Fragen für das Graduierungssystem
        Schema::create('grading_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_system_id')->constrained('grading_systems')->onDelete('cascade');
            $table->text('question');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Dokumentationssessions (Gruppe oder einzelner Schüler)
        Schema::create('grading_documentation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klasse_id')->constrained('klassen')->onDelete('cascade');
            $table->foreignId('grading_system_id')->constrained('grading_systems')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Lehrer
            $table->enum('type', ['group', 'individual']); // Gruppendokumentation oder Einzeldokumentation
            $table->foreignId('group_id')->nullable()->constrained('paed_diary_class_groups')->onDelete('cascade'); // optional: wenn Gruppendokumentation
            $table->foreignId('schueler_id')->nullable()->constrained('schueler')->onDelete('cascade'); // nur bei individual
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['klasse_id', 'type', 'completed_at']);
        });

        // Schülerantworten auf Fragen
        Schema::create('grading_student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('grading_documentation_sessions')->onDelete('cascade');
            $table->foreignId('schueler_id')->constrained('schueler')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('grading_questions')->onDelete('cascade');
            $table->integer('self_rating')->nullable(); // 1-5 (Smiley-Skala)
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'schueler_id', 'question_id'], 'gs_answer_unique');
        });

        // Lehrereinschätzungen
        Schema::create('grading_teacher_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('grading_documentation_sessions')->onDelete('cascade');
            $table->foreignId('schueler_id')->constrained('schueler')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('grading_questions')->onDelete('cascade');
            $table->integer('teacher_rating')->nullable(); // 1-5 (Smiley-Skala)
            $table->text('comment')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'schueler_id', 'question_id'], 'gt_assess_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_teacher_assessments');
        Schema::dropIfExists('grading_student_answers');
        Schema::dropIfExists('grading_documentation_sessions');
        Schema::dropIfExists('grading_questions');
    }
};
