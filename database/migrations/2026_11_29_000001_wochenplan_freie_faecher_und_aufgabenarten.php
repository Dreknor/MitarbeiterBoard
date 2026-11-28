<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wochenplan:
 * - freie Fach-Felder (Plan-Fach ohne Katalog-Fach, eigener Name + Symbol),
 * - freiwillige Aufgaben (im PDF mit *) und Empfehlungen für die Freiarbeit,
 * - Kürzel je täglicher Übung (Spaltenkopf im Wochenraster).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_plan_faecher', function (Blueprint $table) {
            $table->unsignedBigInteger('wp_fach_id')->nullable()->change();
            $table->string('symbol', 40)->nullable()->after('custom_name');
        });

        Schema::table('wp_aufgaben', function (Blueprint $table) {
            $table->boolean('freiwillig')->default(false)->after('link');
            $table->boolean('freiarbeit')->default(false)->after('freiwillig');
        });

        Schema::table('wp_taegliche_uebungen', function (Blueprint $table) {
            $table->string('kuerzel', 12)->nullable()->after('aufgabe');
        });
    }

    public function down(): void
    {
        Schema::table('wp_taegliche_uebungen', function (Blueprint $table) {
            $table->dropColumn('kuerzel');
        });

        Schema::table('wp_aufgaben', function (Blueprint $table) {
            $table->dropColumn(['freiwillig', 'freiarbeit']);
        });

        Schema::table('wp_plan_faecher', function (Blueprint $table) {
            $table->dropColumn('symbol');
        });
        // wp_fach_id bleibt nullable: freie Felder würden sonst verloren gehen.
    }
};
