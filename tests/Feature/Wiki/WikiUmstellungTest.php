<?php

namespace Tests\Feature\Wiki;

use App\Models\User;
use App\Models\WikiSeite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Übernahme des alten Wikis (wiki_sites) durch die Migration 2026_11_26_000002_wiki_daten_uebernehmen.
 */
class WikiUmstellungTest extends TestCase
{
    private function altesWiki(array $zeilen): void
    {
        DB::table('wiki_links')->delete();
        DB::table('wiki_weiterleitungen')->delete();
        DB::table('wiki_revisionen')->delete();
        DB::table('wiki_seiten')->update(['parent_id' => null]);
        DB::table('wiki_seiten')->delete();
        DB::table('wiki_sites')->delete();

        $standardAutor = User::factory()->create()->id;
        foreach ($zeilen as $i => $z) {
            DB::table('wiki_sites')->insert(array_merge([
                'author_id' => $standardAutor,
                'text' => '<p>Text</p>',
                'created_at' => now()->subDays(100 - $i),
                'updated_at' => now()->subDays(100 - $i),
            ], $z));
        }

        (require database_path('migrations/2026_11_26_000002_wiki_daten_uebernehmen.php'))->up();
    }

    public function test_fassungen_werden_revisionen_und_praefixe_zu_elternseiten(): void
    {
        Storage::fake('wiki');
        $autor = User::factory()->create();
        $png = base64_encode(\Illuminate\Http\UploadedFile::fake()->image('x.png', 10, 10)->getContent());

        $this->altesWiki([
            ['title' => 'Start', 'text' => '<p>Willkommen. <a href="https://'.parse_url(config('app.url'), PHP_URL_HOST).'/wiki/Moodle:-neuer-Kurs">Kurs</a></p>'],
            ['title' => 'Moodle: neuer Kurs', 'text' => '<p>Alt</p>', 'author_id' => $autor->id],
            ['title' => 'Moodle: neuer Kurs', 'text' => '<p>Neu <img src="data:image/png;base64,'.$png.'"></p>', 'author_id' => $autor->id],
            ['title' => 'Moodle: Kalender', 'text' => '<p>Kalender in Moodle</p>'],
            ['title' => 'Kalender', 'text' => '<p>Kalender im MitarbeiterBoard</p>'],
            ['title' => 'MitarbeiterBoard: Abos', 'text' => '<p>Abos</p>'],
            ['title' => 'MitarbeiterBoard: Meetings', 'text' => '<p>Meetings</p>'],
            ['title' => 'Nextcloud Talk', 'text' => '<p>Talk</p>'],
            ['title' => 'Nextcloud Talk: Verfügbarkeit', 'text' => '<p>Status</p>'],
            ['title' => 'DLLP: Übersicht', 'text' => '<p>Einzeln</p>'],
            ['title' => 'Test-Wiki-Seite', 'text' => '<p>Bindestrich</p>'],
            ['title' => 'Gelöscht', 'text' => '<p>weg</p>', 'deleted_at' => now()],
        ]);

        $kurs = WikiSeite::where('titel', 'Neuer Kurs')->sole();
        $moodle = WikiSeite::where('titel', 'Moodle')->sole();
        $this->assertSame($moodle->id, $kurs->parent_id);
        $this->assertSame(2, $kurs->revisionen()->count());
        $this->assertSame($autor->id, $kurs->geaendert_von);
        $this->assertStringContainsString('/wiki/anhang/', $kurs->inhalt);
        $this->assertStringNotContainsString('base64', $kurs->inhalt);

        // Systemseite behält ihren Slug, die gleichnamige Moodle-Seite bekommt das Präfix
        $this->assertTrue(WikiSeite::where('slug', 'kalender')->sole()->systemseite);
        $this->assertSame($moodle->id, WikiSeite::where('slug', 'moodle-kalender')->sole()->parent_id);

        // vorhandene Seite wird Elternseite, Einzelpräfix bleibt unverändert
        $talk = WikiSeite::where('titel', 'Nextcloud Talk')->sole();
        $this->assertSame($talk->id, WikiSeite::where('titel', 'Verfügbarkeit')->sole()->parent_id);
        $this->assertNull(WikiSeite::where('titel', 'DLLP: Übersicht')->sole()->parent_id);
        // „MitarbeiterBoard“: Abos, Meetings und das Modul-Handbuch Kalender (Systemseite)
        $this->assertEqualsCanonicalizing(['Abos', 'Meetings', 'Kalender'], WikiSeite::where('titel', 'Abos')->sole()->eltern->kinder()->pluck('titel')->all());

        $this->assertFalse(WikiSeite::where('titel', 'Gelöscht')->exists());
        $this->assertSame('start', WikiSeite::where('titel', 'Start')->sole()->slug);

        // interner Link umgeschrieben, alte URLs leiten weiter
        $this->assertStringContainsString('href="/wiki/'.$kurs->slug.'"', WikiSeite::where('slug', 'start')->sole()->inhalt);
        $this->actingAsWithPermission('view wiki');
        $this->get('/wiki/Moodle:-neuer-Kurs')->assertRedirect(route('wiki.show', $kurs->slug));
        $this->get('/wiki/Test-Wiki-Seite')->assertRedirect(route('wiki.show', 'test-wiki-seite'));
        $this->get(route('wiki.show', 'test-wiki-seite'))->assertOk()->assertSee('Bindestrich');
    }
}
