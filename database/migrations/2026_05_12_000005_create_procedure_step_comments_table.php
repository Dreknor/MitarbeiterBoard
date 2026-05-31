<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 / §8.3 – Kommentar-Funktion an Schritten mit Mailbenachrichtigung.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('procedure_step_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('body');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('step_id')->references('id')->on('procedure_steps')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_step_comments');
    }
};

