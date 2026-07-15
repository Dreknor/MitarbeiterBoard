<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paed_diary_goals')) {
            Schema::create('paed_diary_goals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('schueler_id');
                $table->unsignedBigInteger('user_id');
                $table->text('goal_text');
                $table->timestamp('achieved_at')->nullable();
                $table->unsignedBigInteger('achieved_by')->nullable();
                $table->timestamps();

                $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('achieved_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['schueler_id', 'created_at'], 'pd_goals_schueler_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paed_diary_goals');
    }
};

