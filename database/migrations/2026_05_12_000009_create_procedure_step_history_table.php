<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verlauf-Tabelle für Prozessschritte.
 * Erfasst Änderungen an Verantwortlichkeiten (user_added, user_removed, position_changed)
 * sowie Statuswechsel (reopened). "completed" wird aus den Modellfeldern gelesen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_step_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->string('type', 50); // user_added | user_removed | position_changed | reopened
            $table->unsignedInteger('performed_by')->nullable(); // Wer hat die Aktion ausgeführt
            $table->json('meta')->nullable();                    // Kontextdaten (Name, Position etc.)
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('step_id')->references('id')->on('procedure_steps')->onDelete('cascade');
            $table->index(['step_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_step_history');
    }
};

