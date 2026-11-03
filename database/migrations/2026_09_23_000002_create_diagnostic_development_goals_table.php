<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API v1: Individuelle Entwicklungsziele eines Schülers (Förderziele mit Zieldatum und Status).
 *
 * Ergänzt den bestehenden Kriterienkatalog (diagnostic_goals = Katalogziele je Stufe),
 * der keine schülerbezogenen Felder wie Zieldatum, Status oder Abschlussnotiz kennt.
 * Ein Entwicklungsziel kann optional auf ein Katalogziel verweisen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('diagnostic_development_goals')) {
            return;
        }

        Schema::create('diagnostic_development_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schueler_id');
            $table->unsignedBigInteger('diagnostic_session_id')->nullable();
            $table->unsignedBigInteger('diagnostic_area_id')->nullable();
            $table->unsignedBigInteger('diagnostic_stage_id')->nullable();
            $table->unsignedBigInteger('diagnostic_goal_id')->nullable();
            $table->string('title', 500);
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('completion_notes')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
            $table->foreign('diagnostic_session_id')->references('id')->on('diagnostic_sessions')->onDelete('set null');
            $table->foreign('diagnostic_area_id')->references('id')->on('diagnostic_areas')->onDelete('set null');
            $table->foreign('diagnostic_stage_id')->references('id')->on('diagnostic_stages')->onDelete('set null');
            $table->foreign('diagnostic_goal_id')->references('id')->on('diagnostic_goals')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['schueler_id', 'status'], 'ddg_schueler_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_development_goals');
    }
};
