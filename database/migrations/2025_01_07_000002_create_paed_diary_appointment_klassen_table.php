<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paed_diary_appointment_klassen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paed_diary_appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('klasse_id')->constrained('klassen')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('paed_diary_appointment_klassen');
    }
};
