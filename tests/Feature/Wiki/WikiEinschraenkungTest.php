<?php

namespace Tests\Feature\Wiki;

use App\Models\Group;
use App\Models\WikiSeite;
use App\Services\Search\GlobalSearchService;
use App\Services\Wiki\WikiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WikiEinschraenkungTest extends TestCase
{
    private function aufbau(): array
    {
        $verwaltung = $this->createUserWithPermission('view wiki', 'edit wiki', 'manage wiki');
        $wiki = app(WikiService::class);
        $geheim = $wiki->anlegen($verwaltung, ['titel' => 'Kennwörter', 'inhalt' => '<p>Geheimwort Zebra</p>']);
        $unter = $wiki->anlegen($verwaltung, ['titel' => 'Server', 'inhalt' => '<p>Zebra Server</p>', 'parent_id' => $geheim->id]);
        $offen = $wiki->anlegen($verwaltung, ['titel' => 'Öffentlich', 'inhalt' => '<p><a href="/wiki/kennwoerter">Kennwörter</a></p>']);

        $rolle = Role::findOrCreate('IT-Team');
        $wiki->einschraenken($geheim, [$rolle->id], []);

        return compact('verwaltung', 'geheim', 'unter', 'offen', 'rolle');
    }

    public function test_eingeschraenkte_seiten_sind_fuer_andere_unsichtbar(): void
    {
        ['geheim' => $geheim, 'unter' => $unter, 'offen' => $offen] = $this->aufbau();
        $leser = $this->actingAsWithPermission('view wiki', 'edit wiki');

        $this->get(route('wiki.show', $geheim->slug))->assertForbidden();
        $this->get(route('wiki.show', $unter->slug))->assertForbidden();
        $this->get(route('wiki.edit', $unter->slug))->assertForbidden();

        $this->get(route('wiki'))->assertOk()->assertDontSee('Kennwörter');
        $this->get(route('wiki.alle'))->assertDontSee('Server');
        $this->assertSame([], $this->getJson(route('wiki.suche', ['q' => 'Zebra']))->json('treffer'));
        $this->assertSame([], app(GlobalSearchService::class)->search($leser, 'Zebra'));

        // Link auf die gesperrte Seite verrät den Titel nicht
        $this->get(route('wiki.show', $offen->slug))->assertSee('wiki-link-gesperrt', false);

        // Unterseite darf nicht unter die gesperrte Seite gehängt werden
        $this->postJson(route('wiki.store'), ['titel' => 'Trick', 'parent_id' => $geheim->id])->assertStatus(422);
    }

    public function test_freigegebene_rolle_und_gruppe_sehen_die_seiten(): void
    {
        ['geheim' => $geheim, 'unter' => $unter, 'rolle' => $rolle] = $this->aufbau();

        $mitRolle = $this->actingAsWithPermission('view wiki');
        $mitRolle->assignRole($rolle);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->get(route('wiki.show', $unter->slug))->assertOk()->assertSee('eingeschränkt');
        $this->assertCount(2, collect($this->getJson(route('wiki.suche', ['q' => 'Zebra']))->json('treffer')));

        $gruppe = Group::factory()->create();
        app(WikiService::class)->einschraenken($geheim, [], [$gruppe->id]);
        $this->get(route('wiki.show', $geheim->slug))->assertForbidden();

        $gruppe->users()->attach($mitRolle->id);
        $this->get(route('wiki.show', $geheim->slug))->assertOk();
    }

    public function test_unterseite_kann_weiter_eingeschraenkt_aber_nicht_geoeffnet_werden(): void
    {
        ['geheim' => $geheim, 'unter' => $unter, 'rolle' => $rolle] = $this->aufbau();
        $andereRolle = Role::findOrCreate('Schulleitung');
        app(WikiService::class)->einschraenken($unter, [$andereRolle->id], []);

        $nurIt = $this->actingAsWithPermission('view wiki');
        $nurIt->assignRole($rolle);
        $this->get(route('wiki.show', $geheim->slug))->assertOk();
        $this->get(route('wiki.show', $unter->slug))->assertForbidden();

        $nurSl = $this->actingAsWithPermission('view wiki');
        $nurSl->assignRole($andereRolle);
        $this->get(route('wiki.show', $unter->slug))->assertForbidden();
    }

    public function test_verwaltung_sieht_alles_und_stellt_ein(): void
    {
        ['verwaltung' => $verwaltung, 'geheim' => $geheim, 'rolle' => $rolle] = $this->aufbau();
        $this->actingAs($verwaltung);

        $this->get(route('wiki.show', $geheim->slug))->assertOk();
        $this->get(route('wiki.einstellungen', $geheim))->assertOk()->assertSee('IT-Team');

        $this->post(route('wiki.einschraenkung', $geheim), ['rollen' => [], 'gruppen' => []])->assertRedirect();
        $this->assertFalse($geheim->einschraenkungen()->exists());

        $this->actingAsWithPermission('view wiki');
        $this->get(route('wiki.show', $geheim->slug))->assertOk();
        $this->get(route('wiki.einstellungen', $geheim))->assertForbidden();
    }

    public function test_dashboard_karte_zeigt_keine_gesperrten_seiten(): void
    {
        $this->aufbau();
        $this->actingAsWithPermission('view wiki');

        $html = view('wiki.dashboardCard-v2')->render();
        $this->assertStringNotContainsString('Kennwörter', $html);
        $this->assertStringContainsString('Öffentlich', $html);
    }
}
