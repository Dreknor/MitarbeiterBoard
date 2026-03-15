<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_calendar_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ox_calendar_id')
                ->constrained('ox_calendars')
                ->cascadeOnDelete();
            $table->string('farbe', 7);
            $table->timestamps();

            $table->unique(['user_id', 'ox_calendar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_calendar_colors');
    }
};

