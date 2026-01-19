<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grading_student_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('schueler_id');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['token', 'expires_at']);
            $table->index('session_id');
            $table->index('schueler_id');

            // Note: Foreign keys removed to avoid migration issues
            // The relationship is maintained at the application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_student_tokens');
    }
};
