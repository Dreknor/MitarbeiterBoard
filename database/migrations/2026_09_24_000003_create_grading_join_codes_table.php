<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API v1 (Pädagogen-App): Selbsteinschätzung auf Schüler-iPads.
 *
 * - grading_join_codes:       Beitrittscode je Schüler und Session (6 Zeichen, max. 8 h gültig)
 * - grading_student_devices:  Schüler-Gerät nach dem Beitritt. Ist Besitzer (tokenable) des
 *                             Sanctum-Tokens, damit Schüler-Tokens nie Rechte eines Benutzers erben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_join_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('grading_documentation_sessions')->cascadeOnDelete();
            $table->foreignId('schueler_id')->constrained('schueler')->cascadeOnDelete();
            $table->char('code', 6)->unique();
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['session_id', 'schueler_id']);
        });

        Schema::create('grading_student_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('join_code_id')->constrained('grading_join_codes')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('grading_documentation_sessions')->cascadeOnDelete();
            $table->foreignId('schueler_id')->constrained('schueler')->cascadeOnDelete();
            $table->string('device_name', 100);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            \Illuminate\Support\Facades\DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\GradingStudentDevice')
                ->delete();
        }

        Schema::dropIfExists('grading_student_devices');
        Schema::dropIfExists('grading_join_codes');
    }
};
