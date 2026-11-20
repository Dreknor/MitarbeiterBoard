<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wiederkehrende Abwesenheiten: die Regel liegt in absence_series, jeder Termin ist eine normale
 * Abwesenheit (absences.serie_id). Die Regel bleibt erhalten, auch wenn die Termine gelöscht werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('users_id');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('reason', 64);
            $table->string('rhythmus', 16); // woechentlich|monatlich
            $table->json('wochentage')->nullable(); // ISO 1 (Mo) – 5 (Fr), nur woechentlich
            $table->unsignedTinyInteger('intervall')->default(1); // alle N Wochen
            $table->tinyInteger('monat_position')->nullable(); // 1–4, -1 = letzter
            $table->unsignedTinyInteger('monat_wochentag')->nullable(); // ISO 1–5
            $table->date('start');
            $table->date('end');
            $table->boolean('showVertretungsplan')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('users_id');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->unsignedBigInteger('serie_id')->nullable()->after('holiday_id');
            $table->index('serie_id');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropIndex(['serie_id']);
            $table->dropColumn('serie_id');
        });

        Schema::dropIfExists('absence_series');
    }
};
