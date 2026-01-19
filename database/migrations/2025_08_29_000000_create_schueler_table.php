<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schueler', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('klasse_id');
            $table->string('vorname');
            $table->string('nachname');
            $table->date('geburtsdatum')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('klasse_id')->references('id')->on('klassen');
            $table->index('klasse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schueler');
    }
};

