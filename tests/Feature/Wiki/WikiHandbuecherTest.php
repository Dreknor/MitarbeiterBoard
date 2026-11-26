<?php

namespace Tests\Feature\Wiki;

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Tests\TestCase;

class WikiHandbuecherTest extends TestCase
{
    private const NEU = [
        'erste-schritte', 'nachrichten', 'ticketsystem', 'themen-und-aufgaben', 'meetings',
        'boards', 'urlaub', 'erstellen-von-abwesenheiten', 'abos',
    ];

    public function test_handbuecher_sind_systemseiten_unter_mitarbeiterboard(): void
    {
        $eltern = WikiSeite::where('slug', 'mitarbeiterboard')->firstOrFail();

        foreach (self::NEU as $slug) {
            $seite = WikiSeite::where('slug', $slug)->first();
            $this->assertNotNull($seite, $slug);
            $this->assertTrue($seite->systemseite, $slug);
            $this->assertSame($eltern->id, $seite->parent_id, $slug);
            $this->assertStringContainsString('Kurz gesagt', $seite->inhalt_text, $slug);
            $this->assertStringContainsString('<h2', $seite->inhalt, $slug);
        }
        $this->assertSame('Abwesenheiten melden', WikiSeite::where('slug', 'erstellen-von-abwesenheiten')->value('titel'));
    }

    public function test_verweise_zwischen_den_handbuechern_sind_aufgeloest(): void
    {
        $offen = \App\Models\WikiLink::whereIn('von_seite_id', WikiSeite::whereIn('slug', self::NEU)->pluck('id'))
            ->whereIn('ziel_slug', self::NEU)
            ->whereNull('ziel_seite_id')
            ->pluck('ziel_slug');

        $this->assertSame([], $offen->all());
    }

    public function test_bestehender_artikel_bekommt_neue_fassung_und_behaelt_verlauf(): void
    {
        $user = $this->createUserWithPermission('view wiki', 'edit wiki');
        $wiki = app(WikiService::class);
        $alt = WikiSeite::where('slug', 'meetings')->firstOrFail();
        $alt->forceDelete();
        $seite = $wiki->anlegen($user, ['titel' => 'Meetings', 'inhalt' => '<p>Jedes Meeting ist einer Gruppe zugeordnet.</p>']);

        $migration = require database_path('migrations/2026_11_26_000003_wiki_mitarbeiterboard_handbuecher.php');
        $migration->up();

        $seite->refresh();
        $this->assertTrue($seite->systemseite);
        $this->assertStringNotContainsString('Jedes Meeting ist einer Gruppe zugeordnet', $seite->inhalt);
        $this->assertSame(2, $seite->revisionen()->count());

        $this->actingAs($user)->get(route('wiki.show', 'meetings'))->assertOk()->assertSee('Kurz gesagt');
    }
}
