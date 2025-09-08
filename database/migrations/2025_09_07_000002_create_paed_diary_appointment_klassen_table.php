<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('paed_diary_appointment_klassen')) {
            Schema::create('paed_diary_appointment_klassen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paed_diary_appointment_id')
                    ->constrained('paed_diary_appointments')
                    ->onDelete('cascade')
                    ->name('fk_pda_klassen_appointment');
                $table->foreignId('klasse_id')
                    ->constrained('klassen')
                    ->onDelete('cascade')
                    ->name('fk_pda_klassen_class');
                $table->timestamps();
            });
        }

    }

    public function down()
    {
        Schema::dropIfExists('paed_diary_appointment_klassen');
    }
};
