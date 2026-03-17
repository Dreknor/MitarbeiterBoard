<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *
 * Erstellt die Tabelle `paed_diary_schueler_absences`, die speichert,
 * welche Schüler an welchem Tag in welcher Klasse als abwesend markiert wurden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paed_diary_schueler_absences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schueler_id');
            $table->unsignedBigInteger('klasse_id');
            $table->date('datum');
            $table->unsignedBigInteger('marked_by'); // User, der die Abwesenheit gesetzt hat
            $table->timestamps();

            $table->foreign('schueler_id')
                  ->references('id')->on('schueler')
                  ->onDelete('cascade');

            $table->foreign('klasse_id')
                  ->references('id')->on('klassen')
                  ->onDelete('cascade');

            $table->foreign('marked_by')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->unique(
                ['schueler_id', 'klasse_id', 'datum'],
                'pdsa_stu_klasse_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paed_diary_schueler_absences');
    }
};

