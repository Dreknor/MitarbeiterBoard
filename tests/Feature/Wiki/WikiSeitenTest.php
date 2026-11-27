<?php

namespace Tests\Feature\Wiki;

use App\Models\User;
use App\Models\WikiRevision;
use App\Models\WikiSeite;
use App\Models\WikiTag;
use App\Models\WikiWeiterleitung;
use App\Services\Wiki\WikiService;
use Tests\TestCase;

class WikiSeitenTest extends TestCase
{
    private function wiki(): WikiService
    {
        return app(WikiService::class);
    }

    private function seite(User $user, string $titel, string $inhalt = '<p>Text</p>', array $mehr = []): WikiSeite
    {
        return $this->wiki()->anlegen($user, array_merge(['titel' => $titel, 'inhalt' => $inhalt], $mehr));
    }

    public function test_startseite_und_seite_lassen_sich_lesen(): void
    {
        $user = $this->actingAsWithPermission('view wiki');
        $seite = $this->seite($user, 'Drucker einrichten', '<h2>Schritt eins</h2><p>Hallo</p><h2>Schritt zwei</h2><p>Welt</p>');

        $this->get(route('wiki'))->assertOk()->assertSee('Drucker einrichten');
        $this->get(route('wiki.show', $seite->slug))
            ->assertOk()
            ->assertSee('Schritt eins')
            ->assertSee('id="schritt-zwei"', false)
            ->assertSee('Auf dieser Seite');
    }

    public function test_ohne_leserecht_kein_zugriff(): void
    {
        $autor = $this->createUserWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($autor, 'Geheim');
        $this->actingAsWithPermission('use dashboard v2');

        $this->get(route('wiki'))->assertForbidden();
        $this->get(route('wiki.show', $seite->slug))->assertForbidden();
    }

    public function test_nur_mit_edit_wiki_anlegen_und_bearbeiten(): void
    {
        $leser = $this->actingAsWithPermission('view wiki');
        $seite = $this->seite($leser, 'Lesbar');

        $this->get(route('wiki.create'))->assertForbidden();
        $this->postJson(route('wiki.store'), ['titel' => 'Neu'])->assertForbidden();
        $this->get(route('wiki.edit', $seite->slug))->assertForbidden();
        $this->putJson(route('wiki.update', $seite), ['titel' => 'X', 'version' => 1])->assertForbidden();
    }

    public function test_anlegen_bereinigt_html_und_legt_revision_an(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');

        $antwort = $this->postJson(route('wiki.store'), [
            'titel' => 'Neue  Seite',
            'inhalt' => '<p onclick="alert(1)">Hallo<script>alert(2)</script></p><div class="wiki-hinweis wiki-hinweis-tipp böse">Tipp</div>',
            'tags' => ['IT', 'it', 'Anleitung'],
            'notiz' => 'erste Fassung',
        ])->assertCreated();

        $seite = WikiSeite::findOrFail($antwort->json('id'));
        $this->assertSame('Neue Seite', $seite->titel);
        $this->assertSame('neue-seite', $seite->slug);
        $this->assertStringNotContainsString('script', $seite->inhalt);
        $this->assertStringNotContainsString('onclick', $seite->inhalt);
        $this->assertStringContainsString('wiki-hinweis wiki-hinweis-tipp', $seite->inhalt);
        $this->assertSame(['Anleitung', 'IT'], $seite->tags->pluck('name')->all());
        $this->assertSame(1, $seite->revisionen()->count());
        $this->assertSame('erste Fassung', $seite->revisionen()->first()->notiz);
        $this->assertSame($user->id, $seite->erstellt_von);
    }

    public function test_umbenennen_aendert_slug_und_alte_url_leitet_weiter(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($user, 'Alter Name');

        $this->putJson(route('wiki.update', $seite), ['titel' => 'Neuer Name', 'inhalt' => '<p>Text</p>', 'version' => $seite->version])
            ->assertOk()
            ->assertJsonPath('slug', 'neuer-name');

        $this->assertTrue(WikiWeiterleitung::where('alter_slug', 'alter-name')->exists());
        $this->get('/wiki/alter-name')->assertRedirect(route('wiki.show', 'neuer-name'));
    }

    public function test_versionskonflikt_liefert_409_mit_vergleich(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($user, 'Konflikt', '<p>Original</p>');

        $this->putJson(route('wiki.update', $seite), ['titel' => 'Konflikt', 'inhalt' => '<p>Erste Änderung</p>', 'version' => 1])->assertOk();

        $this->putJson(route('wiki.update', $seite), ['titel' => 'Konflikt', 'inhalt' => '<p>Meine Änderung</p>', 'version' => 1])
            ->assertStatus(409)
            ->assertJsonPath('konflikt.version', 2)
            ->assertJsonStructure(['konflikt' => ['diff', 'inhalt', 'von']]);

        $this->assertStringContainsString('Erste Änderung', $seite->fresh()->inhalt);
    }

    public function test_ohne_aenderung_keine_neue_revision(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($user, 'Gleich', '<p>Text</p>');

        $this->putJson(route('wiki.update', $seite), ['titel' => 'Gleich', 'inhalt' => '<p>Text</p>', 'version' => 1])->assertOk();

        $this->assertSame(1, $seite->revisionen()->count());
        $this->assertSame(1, $seite->fresh()->version);
    }

    public function test_verlauf_vergleich_und_wiederherstellen(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($user, 'Verlauf', '<p>Version eins</p>');
        $erste = $seite->revisionen()->first();
        $this->wiki()->speichern($seite, $user, ['inhalt' => '<p>Version zwei</p>'], 1);

        $this->get(route('wiki.verlauf', $seite->slug))->assertOk()->assertSee($user->name);
        $this->get(route('wiki.revision', [$seite->slug, $erste]))->assertOk()->assertSee('Version eins');
        $this->get(route('wiki.vergleich', $seite->slug))->assertOk()->assertSee('<del>eins</del>', false)->assertSee('<ins>zwei</ins>', false);

        $this->post(route('wiki.wiederherstellen', [$seite, $erste]))->assertRedirect();

        $seite->refresh();
        $this->assertStringContainsString('Version eins', $seite->inhalt);
        $this->assertSame(3, $seite->revisionen()->count());
        $this->assertSame($erste->id, $seite->revisionen()->first()->wiederhergestellt_aus);
    }

    public function test_geschuetzte_seite_nur_fuer_verwaltung(): void
    {
        $verwaltung = $this->createUserWithPermission('view wiki', 'edit wiki', 'manage wiki');
        $seite = $this->seite($verwaltung, 'Geschützt');
        $this->wiki()->schuetzen($seite, true);

        $this->actingAsWithPermission('view wiki', 'edit wiki');
        $this->get(route('wiki.edit', $seite->slug))->assertForbidden();

        $this->actingAs($verwaltung);
        $this->get(route('wiki.edit', $seite->slug))->assertOk();
    }

    public function test_eltern_seite_und_zyklus(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $eltern = $this->seite($user, 'Eltern');
        $kind = $this->seite($user, 'Kind', '<p>x</p>', ['parent_id' => $eltern->id]);

        $this->get(route('wiki.show', $kind->slug))->assertOk()->assertSee('Eltern');
        $this->get(route('wiki.show', $eltern->slug))->assertSee('Unterseiten');

        $this->putJson(route('wiki.update', $eltern->fresh()), ['titel' => 'Eltern', 'inhalt' => '<p>Text</p>', 'parent_id' => $kind->id, 'version' => 1])
            ->assertStatus(422);
    }

    public function test_rote_links_und_backlinks(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $ziel = $this->seite($user, 'Ziel');
        $quelle = $this->seite($user, 'Quelle', '<p><a href="/wiki/ziel">Ziel</a> und <a href="/wiki/gibt-es-nicht">Fehlt</a></p>');

        $this->get(route('wiki.show', $quelle->slug))->assertSee('wiki-link-rot', false);
        $this->get(route('wiki.show', $ziel->slug))->assertSee('Verlinkt von')->assertSee('Quelle');

        $neu = $this->seite($user, 'Gibt es nicht');
        $this->assertSame('gibt-es-nicht', $neu->slug);
        $this->assertDatabaseHas('wiki_links', ['von_seite_id' => $quelle->id, 'ziel_slug' => 'gibt-es-nicht', 'ziel_seite_id' => $neu->id]);
    }

    public function test_fehlende_seite_fuehrt_zum_anlegen(): void
    {
        $this->actingAsWithPermission('view wiki', 'edit wiki');
        $this->get('/wiki/noch-leer')->assertRedirect(route('wiki.create', ['titel' => 'noch leer']));

        $this->actingAsWithPermission('view wiki');
        $this->get('/wiki/noch-leer')->assertNotFound();
    }

    public function test_suche_json_und_seite(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $this->seite($user, 'WLAN', '<p>Das Passwort steht im Sekretariat.</p>');
        $this->seite($user, 'Kopierer', '<p>Nichts</p>', ['tags' => ['Sekretariat']]);

        $json = $this->getJson(route('wiki.suche', ['q' => 'sekretariat']))->assertOk()->json('treffer');
        $this->assertEqualsCanonicalizing(['WLAN', 'Kopierer'], collect($json)->pluck('titel')->intersect(['WLAN', 'Kopierer'])->values()->all());
        $this->assertStringContainsString('<mark>', collect($json)->firstWhere('titel', 'WLAN')['snippet']);

        $this->get(route('wiki.suche', ['q' => 'wlan']))->assertOk()->assertSee('WLAN');
        $this->get(route('wiki.alle'))->assertOk()->assertSee('Kopierer');
        $this->get(route('wiki.aenderungen'))->assertOk()->assertSee('WLAN');
    }

    public function test_favoriten(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($user, 'Favorit');

        $this->postJson(route('wiki.favorit', $seite))->assertOk()->assertJsonPath('favorit', true);
        $this->get(route('wiki'))->assertSee('Meine Favoriten')->assertSee('Favorit');
        $this->postJson(route('wiki.favorit', $seite))->assertJsonPath('favorit', false);
    }

    public function test_papierkorb(): void
    {
        $verwaltung = $this->actingAsWithPermission('view wiki', 'edit wiki', 'manage wiki');
        $eltern = $this->seite($verwaltung, 'Eltern');
        $this->seite($verwaltung, 'Kind', '<p>x</p>', ['parent_id' => $eltern->id]);
        $einzeln = $this->seite($verwaltung, 'Weg damit');

        $this->delete(route('wiki.loeschen', $eltern))->assertSessionHasErrors('seite');
        $this->delete(route('wiki.loeschen', $einzeln))->assertRedirect(route('wiki'));
        $this->assertSoftDeleted($einzeln);

        $this->get(route('wiki.papierkorb'))->assertOk()->assertSee('Weg damit');

        $leser = $this->createUserWithPermission('view wiki');
        $this->actingAs($leser)->get(route('wiki.show', $einzeln->slug))->assertForbidden();

        $this->actingAs($verwaltung)->post(route('wiki.papierkorb.wiederherstellen', $einzeln->id))->assertRedirect();
        $this->assertNotSoftDeleted($einzeln);
    }

    public function test_systemseiten_nicht_loeschbar_und_aktualisierbar(): void
    {
        $verwaltung = $this->actingAsWithPermission('view wiki', 'edit wiki', 'manage wiki');
        $seite = $this->wiki()->systemseite('modul-handbuch', 'Modul-Handbuch', '<p>Erste Fassung</p>');
        $this->wiki()->systemseite('modul-handbuch', 'Modul-Handbuch', '<p>Zweite Fassung</p>');

        $seite->refresh();
        $this->assertTrue($seite->istGeschuetzt());
        $this->assertSame(2, $seite->revisionen()->count());
        $this->assertStringContainsString('Zweite', $seite->inhalt);
        $this->delete(route('wiki.loeschen', $seite))->assertSessionHasErrors('seite');
    }

    public function test_struktur_speichern_und_zyklen_ablehnen(): void
    {
        $verwaltung = $this->actingAsWithPermission('view wiki', 'edit wiki', 'manage wiki');
        $a = $this->seite($verwaltung, 'A');
        $b = $this->seite($verwaltung, 'B');

        $this->get(route('wiki.struktur'))->assertOk();
        $this->postJson(route('wiki.struktur.speichern'), ['knoten' => [
            ['id' => $b->id, 'parent_id' => null, 'sortierung' => 0],
            ['id' => $a->id, 'parent_id' => $b->id, 'sortierung' => 0],
        ]])->assertOk();
        $this->assertSame($b->id, $a->fresh()->parent_id);

        $this->postJson(route('wiki.struktur.speichern'), ['knoten' => [
            ['id' => $b->id, 'parent_id' => $a->id, 'sortierung' => 0],
        ]])->assertStatus(422);

        $this->actingAsWithPermission('view wiki', 'edit wiki');
        $this->get(route('wiki.struktur'))->assertForbidden();
    }

    public function test_tags_umbenennen_und_zusammenfuehren(): void
    {
        $verwaltung = $this->actingAsWithPermission('view wiki', 'edit wiki', 'manage wiki');
        $this->seite($verwaltung, 'Eins', '<p>x</p>', ['tags' => ['Drucker']]);
        $this->seite($verwaltung, 'Zwei', '<p>x</p>', ['tags' => ['Printer', 'Drucker']]);

        $printer = WikiTag::where('name', 'Printer')->first();
        $this->get(route('wiki.tag', $printer))->assertOk()->assertSee('Zwei');
        $this->put(route('wiki.tags.umbenennen', $printer), ['name' => 'drucker'])->assertRedirect();

        $this->assertSame(1, WikiTag::count());
        $this->assertSame(2, WikiTag::first()->seiten()->count());
        $this->get(route('wiki.tags'))->assertOk()->assertSee('Drucker');
    }

    public function test_pdf(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($user, 'Druckbar', '<h2>Teil</h2><p>Inhalt</p>');

        $this->get(route('wiki.pdf', $seite->slug))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_bearbeitungshinweis(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = $this->seite($user, 'Parallel');
        $kollege = $this->createUserWithPermission('view wiki', 'edit wiki');

        $this->actingAs($kollege)->postJson(route('wiki.bearbeitung', $seite))->assertOk()->assertJsonPath('andere', []);
        // Der Name steht JSON-kodiert in der Alpine-Konfiguration – Umlaute/Apostrophe sähen dort anders aus als im HTML.
        $this->actingAs($user)->get(route('wiki.edit', $seite->slug))->assertOk()->assertViewHas('andere', [$kollege->name]);
    }

    public function test_alte_urls_des_bisherigen_wikis(): void
    {
        $this->actingAsWithPermission('view wiki', 'edit wiki');
        $this->get('/wiki/create/Neue-Seite')->assertRedirect(route('wiki.create', ['titel' => 'Neue Seite']));

        $seite = WikiSeite::where('slug', 'benachrichtigungen')->first();
        $this->get('/wiki/Benachrichtigungen/12')->assertRedirect(route('wiki.show', $seite->slug));
    }

    public function test_globale_suche_findet_wiki_seiten(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $this->seite($user, 'Vertretungsregeln', '<p>Wer vertritt wen</p>');

        $ergebnis = app(\App\Services\Search\GlobalSearchService::class)->search($user, 'Vertretungsregeln');
        $this->assertSame('wiki', $ergebnis[0]['key']);
        $this->assertSame('Vertretungsregeln', $ergebnis[0]['items'][0]['title']);
    }
}
