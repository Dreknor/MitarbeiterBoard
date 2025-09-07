<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paed_diary_appointment_schueler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paed_diary_appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('schueler_id')->constrained('schueler')->onDelete('cascade');
            $table->timestamps();

            // Eindeutige Kombination sicherstellen
            $table->unique(['paed_diary_appointment_id', 'schueler_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('paed_diary_appointment_schueler');
    }
};
