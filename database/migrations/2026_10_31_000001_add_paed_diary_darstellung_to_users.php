<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pädagogisches Tagebuch: persönliche Darstellung (bisherige oder neue Ansicht, Layout der neuen
 * Wochenansicht, Schriftgröße, Kategoriefarben, Bewertungsmodus, Hinweis/Tour gesehen).
 * NULL = noch nichts gewählt → bisherige Ansicht mit Hinweis auf die neue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'paed_diary_darstellung')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('paed_diary_darstellung')->nullable()->comment('Darstellungs-Einstellungen Pädagogisches Tagebuch');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'paed_diary_darstellung')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('paed_diary_darstellung');
            });
        }
    }
};
