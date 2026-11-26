<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Models\WikiSite;
use Tests\TestCase;

class WikiEintragTest extends TestCase
{
    /**
     * Die Anleitung „Benachrichtigungen“ ist im Wiki versioniert: Die Update-Migration
     * (2026_11_02) legt eine neue Fassung an, die alte bleibt im Verlauf. Das Wiki zeigt
     * immer die neueste Fassung. Erneutes Ausführen der Migrationen legt nichts doppelt an.
     */
    public function test_wiki_seite_existiert_genau_einmal(): void
    {
        $titel = config('benachrichtigungen.wiki_titel');
        $anzahl = WikiSite::where('title', $titel)->count();
        $this->assertGreaterThanOrEqual(1, $anzahl);

        // Beide Migrationen sind idempotent
        foreach ([
            '2026_10_06_000002_insert_benachrichtigungen_wiki_entry.php',
            '2026_11_02_000001_update_benachrichtigungen_wiki_entry.php',
        ] as $datei) {
            $migration = require database_path('migrations/'.$datei);
            $migration->up();
        }
        $this->assertSame($anzahl, WikiSite::where('title', $titel)->count());

        // Aktuelle Fassung (wie im WikiController: latest(); im Test gleiche Sekunde → id entscheidet)
        $seite = WikiSite::where('title', $titel)->latest()->orderByDesc('id')->first();
        $this->assertStringContainsString('Tagesübersicht', $seite->text);
        $this->assertStringContainsString('am Vorabend', $seite->text);

        // Ältere Fassungen verweisen auf die erste Version (Versionsverlauf)
        $erste = WikiSite::where('title', $titel)->orderBy('id')->first();
        WikiSite::where('title', $titel)->where('id', '!=', $erste->id)->get()
            ->each(fn ($s) => $this->assertSame($erste->id, (int) $s->previous_version));
    }
}
