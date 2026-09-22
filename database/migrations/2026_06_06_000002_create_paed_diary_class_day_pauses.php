<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paed_diary_class_day_pauses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('klasse_id');
            $table->date('date');
            $table->string('reason')->nullable()->default('Veranstaltung');
            $table->unsignedBigInteger('paused_by')->nullable();
            $table->timestamps();

            $table->unique(['klasse_id', 'date'], 'class_day_pause_unique');
            $table->foreign('klasse_id')->references('id')->on('klassen')->onDelete('cascade');
            $table->foreign('paused_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paed_diary_class_day_pauses');
    }
};

