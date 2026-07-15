<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Kurzes Coaching-Protokollfeld je Schüler und Dokumentationssession.
     */
    public function up(): void
    {
        Schema::create('grading_coaching_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('grading_documentation_sessions')->onDelete('cascade');
            $table->foreignId('schueler_id')->constrained('schueler')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('note')->nullable();
            $table->timestamp('noted_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'schueler_id'], 'gcn_session_schueler_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_coaching_notes');
    }
};

