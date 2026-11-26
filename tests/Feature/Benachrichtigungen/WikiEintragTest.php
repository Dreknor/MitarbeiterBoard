<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Models\WikiSeite;
use Tests\TestCase;

class WikiEintragTest extends TestCase
{
    public function test_wiki_seite_ist_systemseite_mit_festem_slug(): void
    {
        $seite = WikiSeite::where('slug', config('benachrichtigungen.wiki_slug'))->sole();

        $this->assertTrue($seite->systemseite);
        $this->assertStringContainsString('Tagesübersicht', $seite->inhalt);
        $this->assertStringContainsString('am Vorabend', $seite->inhalt);
    }
}
