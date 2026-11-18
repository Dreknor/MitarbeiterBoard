<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pädagogisches Tagebuch: Spaltenwerte „bis Wochenende“.
 *
 * - paed_diary_columns.bis_wochenende: Einstellung der Spalte (wird pro Spaltengruppe/Kategorie
 *   gemeinsam geschaltet). Wirkt nur bei Ja/Nein- und Ampel-Spalten.
 * - paed_diary_column_values.bis_wochenende: wird beim Speichern eines Werts aus der Spalte
 *   übernommen. Nur solche Werte gelten ab ihrem Tag bis Freitag weiter – bestehende Werte
 *   (false) bleiben dadurch unverändert, auch wenn die Einstellung später ein- oder
 *   ausgeschaltet wird.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paed_diary_columns', function (Blueprint $table) {
            $table->boolean('bis_wochenende')->default(false)->after('category');
        });

        Schema::table('paed_diary_column_values', function (Blueprint $table) {
            $table->boolean('bis_wochenende')->default(false)->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('paed_diary_column_values', function (Blueprint $table) {
            $table->dropColumn('bis_wochenende');
        });

        Schema::table('paed_diary_columns', function (Blueprint $table) {
            $table->dropColumn('bis_wochenende');
        });
    }
};
