<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boards (Ergebnisboards): Kommentare, Änderungsverlauf und wiederherstellbare Karten.
 *
 * - ergebnis_karten.deleted_at: gelöschte Karten lassen sich 90 Tage wiederherstellen.
 * - ergebnis_kommentare: Kommentare am Board (karte_id = null) oder an einer Karte,
 *   reiner Text, @-Erwähnungen als User-IDs, soft-gelöscht.
 * - ergebnis_board_verlauf: fachliche Einträge (Aktion + Details), geschrieben von
 *   ErgebnisboardVerlaufService. `karte_id` ohne Fremdschlüssel, damit Einträge
 *   endgültig gelöschter Karten erhalten bleiben.
 * Bestehende Boards bekommen einen Starteintrag „angelegt“ (ohne Rückrechnung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ergebnis_karten', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('ergebnis_kommentare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('ergebnis_boards')->cascadeOnDelete();
            $table->foreignId('karte_id')->nullable()->constrained('ergebnis_karten')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('text');
            $table->json('erwaehnungen')->nullable();
            $table->timestamp('bearbeitet_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['board_id', 'karte_id', 'created_at']);
        });

        Schema::create('ergebnis_board_verlauf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('ergebnis_boards')->cascadeOnDelete();
            $table->unsignedBigInteger('karte_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aktion', 40);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['board_id', 'id']);
            $table->index('karte_id');
        });

        DB::table('ergebnis_boards')->orderBy('id')->select(['id', 'created_by', 'titel', 'created_at'])
            ->chunk(200, function ($boards) {
                DB::table('ergebnis_board_verlauf')->insert($boards->map(fn ($b) => [
                    'board_id' => $b->id,
                    'karte_id' => null,
                    'user_id' => $b->created_by,
                    'aktion' => 'board_angelegt',
                    'details' => json_encode(['titel' => $b->titel], JSON_UNESCAPED_UNICODE),
                    'created_at' => $b->created_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ergebnis_board_verlauf');
        Schema::dropIfExists('ergebnis_kommentare');
        Schema::table('ergebnis_karten', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
