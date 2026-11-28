<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angepasste Arbeitsblätter: Protokoll der KI-Aufrufe (Fehlersuche). Bilddaten werden vor dem
 * Speichern durch Platzhalter ersetzt; Einträge werden nach 30 Tagen gelöscht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_ki_protokolle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blatt_id')->nullable()->index();
            $table->string('anbieter', 32);
            $table->string('modell', 100)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('dauer_ms')->default(0);
            $table->longText('anfrage')->nullable();
            $table->longText('antwort')->nullable();
            $table->text('fehler')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_ki_protokolle');
    }
};
