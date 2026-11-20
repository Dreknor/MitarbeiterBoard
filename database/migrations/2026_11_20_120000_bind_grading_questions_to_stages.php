<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Graduierungsfragen an Stufen binden.
 *
 * - grading_question_stage: Zuordnung Frage ↔ Stufe (n:m). Eine Frage ohne Zuordnung gilt für alle Stufen.
 * - grading_session_stages: Stufe eines Schülers in einer Session, festgeschrieben beim ersten
 *   Beantworten/Bewerten (bzw. Abschluss). Bis dahin gilt die aktuelle Stufe des Schülers.
 *   Eigene Tabelle statt grading_session_students, weil eine Gruppensession ohne Einträge dort
 *   „ganze Klasse“ bedeutet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_question_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_question_id')->constrained('grading_questions')->cascadeOnDelete();
            $table->foreignId('grading_stage_id')->constrained('grading_stages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['grading_question_id', 'grading_stage_id'], 'gqs_unique');
        });

        Schema::create('grading_session_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('grading_documentation_sessions')->cascadeOnDelete();
            $table->foreignId('schueler_id')->constrained('schueler')->cascadeOnDelete();
            $table->foreignId('grading_stage_id')->nullable()->constrained('grading_stages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'schueler_id'], 'gss_unique');
        });

        // Offene Sessions: Schüler mit bereits vorhandenen Antworten/Bewertungen erhalten ihre
        // aktuelle Stufe als festgeschriebene Stufe. Abgeschlossene Sessions bleiben ohne Eintrag.
        $offen = DB::table('grading_documentation_sessions')->whereNull('completed_at')->pluck('id');
        if ($offen->isEmpty()) {
            return;
        }

        $paare = DB::table('grading_student_answers')->whereIn('session_id', $offen)->select('session_id', 'schueler_id')
            ->union(DB::table('grading_teacher_assessments')->whereIn('session_id', $offen)->select('session_id', 'schueler_id'))
            ->get();

        $stufen = DB::table('schueler')->whereIn('id', $paare->pluck('schueler_id')->unique())->pluck('grading_stage_id', 'id');
        $jetzt = now();

        $paare->unique(fn ($p) => $p->session_id . '-' . $p->schueler_id)
            ->map(fn ($p) => [
                'session_id' => $p->session_id,
                'schueler_id' => $p->schueler_id,
                'grading_stage_id' => $stufen[$p->schueler_id] ?? null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ])
            ->chunk(500)
            ->each(fn ($chunk) => DB::table('grading_session_stages')->insert($chunk->values()->all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_session_stages');
        Schema::dropIfExists('grading_question_stage');
    }
};
