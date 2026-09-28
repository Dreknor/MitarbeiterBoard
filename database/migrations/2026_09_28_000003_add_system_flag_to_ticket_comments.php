<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ticket_comments.system markiert automatische Verlaufseinträge (Status,
 * Zuweisung, automatisches Schließen …), damit sie im Verlauf kompakt
 * dargestellt und nicht als Antworten gezählt werden.
 *
 * Die Spalte stand zwischenzeitlich in 2026_09_28_000001 – Umgebungen, die
 * jene Migration schon mit Spalte ausgeführt haben, werden hier übersprungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ticket_comments', 'system')) {
            Schema::table('ticket_comments', function (Blueprint $table) {
                $table->boolean('system')->default(false)->after('internal');
            });
        }

        // Automatische Einträge ohne Autor (z. B. automatisches Schließen)
        DB::table('ticket_comments')->whereNull('user_id')->update(['system' => true]);

        // Statuseinträge (alter und neuer Code) anhand des festen Textes erkennen
        DB::table('ticket_comments')
            ->where(function ($q) {
                $q->where('comment', 'Ticket closed')
                    ->orWhere('comment', 'Ticket ist wieder offen')
                    ->orWhere('comment', 'like', 'Ticket ist auf Warten bis %')
                    ->orWhere('comment', 'like', 'Ticket zugewiesen an %')
                    ->orWhere('comment', 'like', 'Ticket von % an % übertragen%')
                    ->orWhere('comment', 'like', 'Zuweisung an % aufgehoben.')
                    ->orWhere('comment', 'like', 'Ticket wartet auf Rückmeldung bis %')
                    ->orWhere('comment', 'Rückmeldung erhalten – Ticket ist wieder offen.')
                    ->orWhere('comment', 'like', 'Ticket geschlossen%')
                    ->orWhere('comment', 'Ticket wieder geöffnet.')
                    ->orWhere('comment', 'like', 'Priorität von %')
                    ->orWhere('comment', 'like', 'Kategorie von %')
                    ->orWhere('comment', 'Titel geändert.');
            })
            ->update(['system' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_comments', 'system')) {
            Schema::table('ticket_comments', function (Blueprint $table) {
                $table->dropColumn('system');
            });
        }
    }
};
