<?php

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Handbuch-Seiten zum MitarbeiterBoard als Systemseiten.
 *
 * Neue Seiten werden angelegt, bestehende Artikel mit gleichem Slug (Abos,
 * Erstellen von Abwesenheiten, Meetings) bekommen eine neue Fassung – die
 * bisherige bleibt im Verlauf. Die Texte liegen in database/wiki/handbuecher.
 */
return new class extends Migration
{
    private const ELTERN = 'mitarbeiterboard';

    /** slug => Titel (Reihenfolge: verlinkende Seite zuerst, Links werden nachträglich angebunden) */
    private const SEITEN = [
        'erste-schritte' => 'Erste Schritte im MitarbeiterBoard',
        'nachrichten' => 'Nachrichten',
        'ticketsystem' => 'Ticketsystem',
        'themen-und-aufgaben' => 'Themen & Aufgaben in Gruppen',
        'meetings' => 'Meetings',
        'boards' => 'Boards',
        'urlaub' => 'Urlaub beantragen',
        'erstellen-von-abwesenheiten' => 'Abwesenheiten melden',
        'abos' => 'Abos',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('wiki_seiten')) {
            return;
        }

        $wiki = app(WikiService::class);

        if (! WikiSeite::withTrashed()->where('slug', self::ELTERN)->exists()) {
            $wiki->systemseite(self::ELTERN, 'MitarbeiterBoard', '<p>Anleitungen zu den Bereichen des MitarbeiterBoards. Neu hier? Beginnen Sie mit <a href="/wiki/erste-schritte">Erste Schritte im MitarbeiterBoard</a>.</p>');
        }

        foreach (self::SEITEN as $slug => $titel) {
            $html = file_get_contents(database_path('wiki/handbuecher/'.$slug.'.html'));
            $wiki->systemseite($slug, $titel, $html, self::ELTERN, 'Handbuch aktualisiert');
        }
    }

    public function down(): void
    {
        // Inhalte bleiben erhalten – frühere Fassungen stehen im Verlauf.
    }
};
