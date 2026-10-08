<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Models\WikiSite;
use Tests\TestCase;

class WikiEintragTest extends TestCase
{
    public function test_wiki_seite_existiert_genau_einmal(): void
    {
        $this->assertSame(1, WikiSite::where('title', config('benachrichtigungen.wiki_titel'))->count());

        // Migration ist idempotent
        $migration = require database_path('migrations/2026_10_06_000002_insert_benachrichtigungen_wiki_entry.php');
        $migration->up();

        $seite = WikiSite::where('title', config('benachrichtigungen.wiki_titel'))->sole();
        $this->assertStringContainsString('Tagesübersicht', $seite->text);
        $this->assertStringContainsString('am Vorabend', $seite->text);
    }
}
