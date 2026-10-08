<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Models\OxCalendar;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use App\Notifications\Push;
use Tests\TestCase;

class BenachrichtigungControllerTest extends TestCase
{
    public function test_verlauf_zeigt_nur_eigene_benachrichtigungen(): void
    {
        $ich = $this->actingAsWithPermission();
        $andere = User::factory()->create();

        $ich->notify(new Push('Meine Meldung', 'für mich', 'aufgaben'));
        $andere->notify(new Push('Fremde Meldung', 'nicht für mich', 'aufgaben'));

        $this->get(route('benachrichtigungen.index'))
            ->assertOk()
            ->assertSee('Meine Meldung')
            ->assertDontSee('Fremde Meldung');
    }

    public function test_filter_nach_kategorie_und_ungelesen(): void
    {
        $ich = $this->actingAsWithPermission();
        $ich->notify(new Push('Ticket-Meldung', 'x', 'tickets'));
        $ich->notify(new Push('Aufgaben-Meldung', 'y', 'aufgaben'));
        $ich->notifications()->where('kategorie', 'aufgaben')->first()->markAsRead();

        $this->get(route('benachrichtigungen.index', ['kategorie' => 'tickets']))
            ->assertSee('Ticket-Meldung')->assertDontSee('Aufgaben-Meldung');

        $this->get(route('benachrichtigungen.index', ['status' => 'ungelesen']))
            ->assertSee('Ticket-Meldung')->assertDontSee('Aufgaben-Meldung');
    }

    public function test_neueste_liefert_json_fuer_glocke(): void
    {
        $ich = $this->actingAsWithPermission();
        $ich->notify(new Push('Hallo', 'Welt', 'aufgaben'));

        $this->getJson(route('benachrichtigungen.neueste'))
            ->assertOk()
            ->assertJsonPath('ungelesen', 1)
            ->assertJsonPath('eintraege.0.titel', 'Hallo')
            ->assertJsonPath('eintraege.0.icon', config('benachrichtigungen.kategorien.aufgaben.icon'));
    }

    public function test_oeffnen_markiert_gelesen_und_leitet_intern_weiter(): void
    {
        $ich = $this->actingAsWithPermission();
        $ich->notify(new Push('Ziel', 'x', 'aufgaben', url('/tickets')));
        $n = $ich->notifications()->first();

        $this->get(route('benachrichtigungen.oeffnen', $n->id))->assertRedirect(url('/tickets'));
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_oeffnen_ignoriert_externe_ziele(): void
    {
        $ich = $this->actingAsWithPermission();
        $ich->notify(new Push('Extern', 'x', 'aufgaben', 'https://evil.example.com/'));
        $n = $ich->notifications()->first();

        $this->get(route('benachrichtigungen.oeffnen', $n->id))->assertRedirect(route('benachrichtigungen.index'));

        // Gleicher Präfix, andere Domain
        $ich->notify(new Push('Praefix', 'x', 'aufgaben', rtrim(url('/'), '/').'.evil.example.com/'));
        $n2 = $ich->notifications()->where('data', 'like', '%Praefix%')->firstOrFail();
        $this->get(route('benachrichtigungen.oeffnen', $n2->id))->assertRedirect(route('benachrichtigungen.index'));
    }

    public function test_fremde_benachrichtigung_kann_nicht_geoeffnet_werden(): void
    {
        $andere = User::factory()->create();
        $andere->notify(new Push('Fremd', 'x', 'aufgaben'));
        $n = $andere->notifications()->first();

        $this->actingAsWithPermission();
        $this->get(route('benachrichtigungen.oeffnen', $n->id))->assertNotFound();
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_alle_als_gelesen_markieren(): void
    {
        $ich = $this->actingAsWithPermission();
        $ich->notify(new Push('A', 'x', 'aufgaben'));
        $ich->notify(new Push('B', 'y', 'aufgaben'));

        $this->postJson(route('benachrichtigungen.gelesen'))->assertOk();
        $this->assertSame(0, $ich->unreadNotifications()->count());
    }

    public function test_einstellungsseite_zeigt_nur_erlaubte_kategorien(): void
    {
        $this->actingAsWithPermission();

        $this->get(route('benachrichtigungen.einstellungen'))
            ->assertOk()
            ->assertSee('Vertretungen')
            ->assertSee('Tagesübersicht')
            ->assertDontSee('Personalverwaltung');
    }

    private function gueltigeDaten(array $tagesvorschau = []): array
    {
        return [
            'kategorien' => [
                'aufgaben' => ['push' => '1', 'mail' => 'zusammenfassung'],
                'themen'   => ['push' => '0', 'mail' => 'aus'],
            ],
            'tagesvorschau' => array_merge([
                'per_mail'            => '1',
                'per_push'            => '0',
                'zeitpunkt'           => 'vorabend',
                'uhrzeit'             => '19:15',
                'bereiche'            => ['vertretungen', 'meetings'],
                'eingeladene_termine' => '0',
            ], $tagesvorschau),
        ];
    }

    public function test_einstellungen_speichern(): void
    {
        $ich = $this->actingAsWithPermission();

        $this->put(route('benachrichtigungen.einstellungen.speichern'), $this->gueltigeDaten())
            ->assertRedirect(route('benachrichtigungen.einstellungen'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', ['user_id' => $ich->id, 'kategorie' => 'aufgaben', 'push' => 1, 'mail' => 'zusammenfassung']);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $ich->id, 'kategorie' => 'themen', 'push' => 0, 'mail' => 'aus']);

        $tv = TagesvorschauEinstellung::where('user_id', $ich->id)->firstOrFail();
        $this->assertSame('vorabend', $tv->zeitpunkt);
        $this->assertSame('19:15', $tv->uhrzeitKurz());
        $this->assertSame(['vertretungen', 'meetings'], $tv->bereiche);
        $this->assertFalse($tv->eingeladene_termine);
    }

    public function test_uhrzeit_muss_zum_zeitpunkt_passen(): void
    {
        $this->actingAsWithPermission();

        $this->put(route('benachrichtigungen.einstellungen.speichern'), $this->gueltigeDaten(['zeitpunkt' => 'morgens', 'uhrzeit' => '19:15']))
            ->assertSessionHasErrors('tagesvorschau.uhrzeit');

        $this->put(route('benachrichtigungen.einstellungen.speichern'), $this->gueltigeDaten(['uhrzeit' => '19:10']))
            ->assertSessionHasErrors('tagesvorschau.uhrzeit');
    }

    public function test_nur_sichtbare_kalender_waehlbar(): void
    {
        $this->actingAsWithPermission('view calendar');
        $oeffentlich = OxCalendar::factory()->create();
        $versteckt = OxCalendar::factory()->create(['sichtbar' => false]);

        $this->put(route('benachrichtigungen.einstellungen.speichern'), $this->gueltigeDaten(['kalender_ids' => [$versteckt->id]]))
            ->assertSessionHasErrors('tagesvorschau.kalender_ids.0');

        $this->put(route('benachrichtigungen.einstellungen.speichern'), $this->gueltigeDaten(['kalender_ids' => [$oeffentlich->id]]))
            ->assertSessionHasNoErrors();
    }

    public function test_abwesenheiten_bereich_ist_opt_in_und_speicherbar(): void
    {
        $ich = $this->actingAsWithPermission('view absences');

        // Ohne Auswahl ist der Bereich nicht vorausgewählt
        $this->get(route('benachrichtigungen.einstellungen'))
            ->assertOk()
            ->assertDontSee('value="abwesenheiten" checked', false);

        $this->put(route('benachrichtigungen.einstellungen.speichern'), $this->gueltigeDaten(['bereiche' => ['abwesenheiten']]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['abwesenheiten'], $ich->fresh()->tagesvorschauEinstellung->bereiche);
    }

    public function test_abwesenheiten_bereich_nur_mit_permission(): void
    {
        $this->actingAsWithPermission();

        $this->put(route('benachrichtigungen.einstellungen.speichern'), $this->gueltigeDaten(['bereiche' => ['abwesenheiten']]))
            ->assertSessionHasErrors('tagesvorschau.bereiche.0');
    }

    public function test_altes_abo_link_leitet_zu_einstellungen(): void
    {
        $this->actingAsWithPermission('view absences');

        $this->get('absences/abo/daily')->assertRedirect(route('benachrichtigungen.einstellungen'));
    }

    public function test_tagesansicht_rendert(): void
    {
        $this->actingAsWithPermission();

        $this->get(route('benachrichtigungen.tag'))->assertOk()->assertSee('Heute');
        $this->get(route('benachrichtigungen.tag', '2026-12-25'))->assertOk()->assertSee('Feiertag');
    }

    public function test_push_abo_speichern_und_entfernen(): void
    {
        $ich = $this->actingAsWithPermission();

        $this->postJson(route('push.store'), [
            'endpoint' => 'https://push.example.test/geraet-1',
            'keys'     => ['auth' => 'auth-token', 'p256dh' => 'public-key'],
        ])->assertOk();
        $this->assertSame(1, $ich->pushSubscriptions()->count());

        $this->deleteJson(route('push.destroy'), ['endpoint' => 'https://push.example.test/geraet-1'])->assertOk();
        $this->assertSame(0, $ich->pushSubscriptions()->count());
    }

    public function test_test_push_ohne_geraet_meldet_fehler(): void
    {
        $this->actingAsWithPermission();

        $this->postJson(route('push.test'))->assertStatus(422);
    }
}
