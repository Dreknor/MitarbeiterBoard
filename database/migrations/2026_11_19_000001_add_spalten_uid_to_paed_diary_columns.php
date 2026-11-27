<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pädagogisches Tagebuch: Verknüpfung der Spalten-Kopien über Klassen hinweg.
 *
 * Eine Spalte bleibt eine Zeile pro Klasse. Alle Kopien derselben Spalte tragen dieselbe
 * spalten_uid – damit lassen sich Name, Gruppe, Typ und die zugeordneten Klassen gemeinsam
 * bearbeiten (PaedDiarySpaltenService).
 *
 * Bestehende Spalten werden verknüpft, wenn Name, Typ und Gruppe übereinstimmen
 * (Name/Gruppe ohne Groß-/Kleinschreibung und Leerzeichen am Rand). Gleicher Name in einer
 * anderen Gruppe oder mit anderem Typ bleibt eine eigenständige Spalte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paed_diary_columns', function (Blueprint $table) {
            $table->string('spalten_uid', 36)->nullable()->after('klasse_id')->index();
        });

        $uids = [];
        DB::table('paed_diary_columns')->orderBy('id')->select(['id', 'name', 'type', 'category'])
            ->chunkById(500, function ($spalten) use (&$uids) {
                foreach ($spalten as $spalte) {
                    $schluessel = mb_strtolower(trim((string) $spalte->name)) . "\0"
                        . (string) $spalte->type . "\0"
                        . mb_strtolower(trim((string) $spalte->category));
                    $uids[$schluessel] ??= (string) Str::uuid();
                    DB::table('paed_diary_columns')->where('id', $spalte->id)->update(['spalten_uid' => $uids[$schluessel]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('paed_diary_columns', function (Blueprint $table) {
            $table->dropIndex(['spalten_uid']);
            $table->dropColumn('spalten_uid');
        });
    }
};
