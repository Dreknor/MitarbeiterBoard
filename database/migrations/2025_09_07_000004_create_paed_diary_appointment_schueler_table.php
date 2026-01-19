<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('paed_diary_appointment_schueler')) {
            Schema::create('paed_diary_appointment_schueler', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paed_diary_appointment_id')
                    ->constrained('paed_diary_appointments')
                    ->onDelete('cascade')
                    ->name('fk_pda_schueler_appointment');
                $table->foreignId('schueler_id')
                    ->constrained('schueler')
                    ->onDelete('cascade')
                    ->name('fk_pda_schueler_student');
                $table->timestamps();

                // Eindeutige Kombination sicherstellen
                $table->unique(['paed_diary_appointment_id', 'schueler_id'], 'uk_pda_schueler_unique');
            });
        }

    }

    public function down()
    {
        Schema::dropIfExists('paed_diary_appointment_schueler');
    }
};
