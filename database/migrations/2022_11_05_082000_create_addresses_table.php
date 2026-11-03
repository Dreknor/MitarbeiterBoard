<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anschriften der Mitarbeitenden.
 *
 * Die Migration war im Repository verloren gegangen, ist in bestehenden Datenbanken aber bereits
 * gelaufen (Eintrag in `migrations`). Sie wird dort daher nicht erneut ausgeführt; der hasTable-Schutz
 * verhindert zusätzlich jede Änderung an einer vorhandenen Tabelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addresses')) {
            return;
        }

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();
            $table->string('strasse')->nullable();
            $table->string('nr')->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('ort')->nullable();
            $table->string('land')->nullable()->default('Deutschland');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
