<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API v1 (Pädagogen-App): Gruppen-Graduierungssessions.
 *
 * - grading_session_students: Teilnehmende Schüler einer Gruppensession inkl. Abschluss je Schüler.
 *   Sessions ohne Einträge (z.B. im Web gestartet) umfassen wie bisher alle Schüler der Klasse.
 * - grading_documentation_sessions.current_question_id: im Modus "by_question" für die
 *   Schüler freigegebene Frage (Selbsteinschätzung auf Schüler-iPads).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_session_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('grading_documentation_sessions')->cascadeOnDelete();
            $table->foreignId('schueler_id')->constrained('schueler')->cascadeOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'schueler_id'], 'gss_session_schueler_unique');
        });

        Schema::table('grading_documentation_sessions', function (Blueprint $table) {
            // Ohne Fremdschlüssel: Fragen können deaktiviert/gelöscht werden, die Session bleibt gültig.
            $table->unsignedBigInteger('current_question_id')->nullable()->after('answer_order_mode');
        });
    }

    public function down(): void
    {
        Schema::table('grading_documentation_sessions', function (Blueprint $table) {
            $table->dropColumn('current_question_id');
        });

        Schema::dropIfExists('grading_session_students');
    }
};
