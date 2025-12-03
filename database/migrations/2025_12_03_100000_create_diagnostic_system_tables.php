<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Bereiche (z.B. "Verhalten", "Kognition")
        Schema::create('diagnostic_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->comment('z.B. "verhalten" oder "kognition"');
            $table->text('description')->nullable()->comment('Bereichsziel');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Stufen (I-V) pro Bereich
        Schema::create('diagnostic_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_area_id')->constrained('diagnostic_areas')->onDelete('cascade');
            $table->string('name')->comment('z.B. "Stufe I"');
            $table->string('code', 10)->comment('z.B. "I", "II", "III", "IV", "V"');
            $table->text('goal_description')->nullable()->comment('Stufenziel');
            $table->integer('sort_order');
            $table->timestamps();
        });

        // Ziele pro Stufe
        Schema::create('diagnostic_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_stage_id')->constrained('diagnostic_stages')->onDelete('cascade');
            $table->string('code', 20)->comment('z.B. "V-1", "V-2"');
            $table->text('description')->comment('Zielbeschreibung inkl. Modalitäten');
            $table->integer('sort_order');
            $table->timestamps();
        });

        // Erfassungs-Sessions (Durchführung)
        Schema::create('diagnostic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schueler_id')->constrained('schueler')->onDelete('cascade');
            $table->foreignId('diagnostic_area_id')->constrained('diagnostic_areas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Ersteller');
            $table->date('session_date')->comment('Datum der Durchführung');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->text('notes')->nullable()->comment('Allgemeine Notizen zur Session');
            $table->timestamps();

            // Nur eine offene Session pro Schüler und Bereich
            $table->unique(['schueler_id', 'diagnostic_area_id', 'is_completed'], 'unique_open_session');
        });

        // Notizen pro Stufe in einer Session
        Schema::create('diagnostic_stage_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_session_id')->constrained('diagnostic_sessions')->onDelete('cascade');
            $table->foreignId('diagnostic_stage_id')->constrained('diagnostic_stages')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['diagnostic_session_id', 'diagnostic_stage_id'], 'unique_session_stage_note');
        });

        // Bewertungen (je Ziel)
        Schema::create('diagnostic_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_session_id')->constrained('diagnostic_sessions')->onDelete('cascade');
            $table->foreignId('diagnostic_goal_id')->constrained('diagnostic_goals')->onDelete('cascade');
            $table->enum('rating', ['white', 'gray', 'dark_gray'])->nullable();
            $table->boolean('is_current_goal')->default(false);
            $table->timestamp('saved_at')->nullable()->comment('Letzter Speicherzeitpunkt');
            $table->timestamps();

            $table->unique(['diagnostic_session_id', 'diagnostic_goal_id'], 'unique_session_goal_assessment');
        });

        // Kommentare zu Zielen für spezifische Schüler
        Schema::create('diagnostic_goal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_goal_id')->constrained('diagnostic_goals')->onDelete('cascade');
            $table->foreignId('schueler_id')->constrained('schueler')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Autor');
            $table->text('comment');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('diagnostic_goal_comments');
        Schema::dropIfExists('diagnostic_assessments');
        Schema::dropIfExists('diagnostic_stage_notes');
        Schema::dropIfExists('diagnostic_sessions');
        Schema::dropIfExists('diagnostic_goals');
        Schema::dropIfExists('diagnostic_stages');
        Schema::dropIfExists('diagnostic_areas');
    }
};

