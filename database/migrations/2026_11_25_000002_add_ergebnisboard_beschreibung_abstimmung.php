<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boards: Kartenbeschreibung (Rich-Text, bereinigt über HtmlSanitizer), Checkliste und
 * Abstimmung („Punkte kleben“).
 *
 * - ergebnis_karten.beschreibung / checkliste ([{id, text, erledigt}]).
 * - ergebnis_boards.abstimmung: {status: aus|laeuft|beendet, punkte: 1–10, verdeckt: bool}.
 * - ergebnis_karten_stimmen: Punkte je Person und Karte (mehrere Punkte auf eine Karte erlaubt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ergebnis_karten', function (Blueprint $table) {
            $table->longText('beschreibung')->nullable()->after('zusatz');
            $table->json('checkliste')->nullable()->after('beschreibung');
        });

        Schema::table('ergebnis_boards', function (Blueprint $table) {
            $table->json('abstimmung')->nullable()->after('legende');
        });

        Schema::create('ergebnis_karten_stimmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('ergebnis_boards')->cascadeOnDelete();
            $table->foreignId('karte_id')->constrained('ergebnis_karten')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('anzahl')->default(1);
            $table->timestamps();

            $table->unique(['karte_id', 'user_id']);
            $table->index(['board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ergebnis_karten_stimmen');
        Schema::table('ergebnis_boards', function (Blueprint $table) {
            $table->dropColumn('abstimmung');
        });
        Schema::table('ergebnis_karten', function (Blueprint $table) {
            $table->dropColumn(['beschreibung', 'checkliste']);
        });
    }
};
