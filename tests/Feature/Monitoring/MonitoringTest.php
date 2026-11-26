<?php

namespace Tests\Feature\Monitoring;

use App\Models\Monitoring\MonitoringDienst;
use App\Models\User;
use App\Notifications\SystemstatusGeaendert;
use App\Services\Monitoring\MonitoringService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

class MonitoringTest extends TestCase
{
    use MocksExternalApis;

    private MonitoringService $monitoring;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->monitoring = app(MonitoringService::class);
        $this->token = $this->monitoring->tokenErzeugen();
    }

    private function heartbeat(array $dienste, ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->postJson('/api/monitoring/heartbeat', ['dienste' => $dienste]);
    }

    private function rolleMitPerson(string $name = 'IT'): array
    {
        $rolle = Role::create(['name' => $name, 'guard_name' => 'web']);
        $person = User::factory()->create();
        $person->assignRole($rolle);

        return [$rolle, $person];
    }

    private function dienst(string $schluessel): MonitoringDienst
    {
        return MonitoringDienst::where('schluessel', $schluessel)->firstOrFail();
    }

    /*
    | Heartbeat-Endpunkt
    */

    public function test_heartbeat_ohne_oder_mit_falschem_token_wird_abgelehnt(): void
    {
        $this->postJson('/api/monitoring/heartbeat', ['dienste' => ['vpn' => 'ok']])->assertStatus(401);
        $this->heartbeat(['vpn' => 'ok'], 'falsch')->assertStatus(401);

        $this->assertSame(0, MonitoringDienst::count());
    }

    public function test_altes_token_ist_nach_neuerzeugung_ungueltig(): void
    {
        $alt = $this->token;
        $this->monitoring->tokenErzeugen();

        $this->heartbeat(['vpn' => 'ok'], $alt)->assertStatus(401);
    }

    public function test_heartbeat_legt_dienste_an_und_setzt_status(): void
    {
        $this->heartbeat([
            'vpn' => ['status' => 'ok', 'latenz_ms' => 23.4],
            'rdp' => 'fehler',
        ])->assertOk()->assertJson(['status' => 'ok', 'aktualisiert' => ['vpn', 'rdp']]);

        $this->assertSame('ok', $this->dienst('heartbeat')->status);
        $this->assertSame('ok', $this->dienst('vpn')->status);
        $this->assertSame(23, $this->dienst('vpn')->latenz_ms);
        $this->assertSame('VPN', $this->dienst('vpn')->name);
        $this->assertSame('fehler', $this->dienst('rdp')->status);
        $this->assertDatabaseHas('monitoring_ereignisse', ['dienst_id' => $this->dienst('rdp')->id, 'status_neu' => 'fehler']);
    }

    public function test_heartbeat_mit_ungueltigen_daten_wird_abgelehnt(): void
    {
        $this->heartbeat(['vpn' => 'kaputt'])->assertStatus(422);
        $this->heartbeat(['Böser Schlüssel' => 'ok'])->assertStatus(422);
        $this->heartbeat(['heartbeat' => 'ok'])->assertStatus(422);

        $this->assertSame(0, MonitoringDienst::where('typ', 'heartbeat')->count());
    }

    public function test_heartbeat_ueberschreibt_keine_webserver(): void
    {
        MonitoringDienst::create(['schluessel' => 'web', 'name' => 'Homepage', 'typ' => 'http', 'url' => 'https://example.org', 'status' => 'ok']);

        $this->heartbeat(['web' => 'fehler'])->assertOk()->assertJson(['ignoriert' => ['web']]);

        $this->assertSame('ok', $this->dienst('web')->status);
    }

    /*
    | Benachrichtigungen
    */

    public function test_stoerung_und_behebung_werden_den_rollen_einmal_gemeldet(): void
    {
        Notification::fake();
        [$rolle, $person] = $this->rolleMitPerson();
        $andere = User::factory()->create();
        $this->monitoring->einstellungSpeichern('monitoring_rollen_benachrichtigen', json_encode([$rolle->id]));

        $this->heartbeat(['vpn' => 'ok']);
        Notification::assertNothingSent();

        $this->heartbeat(['vpn' => ['status' => 'fehler', 'meldung' => 'Ping fehlgeschlagen']]);
        $this->heartbeat(['vpn' => 'fehler']);

        Notification::assertSentToTimes($person, SystemstatusGeaendert::class, 1);
        Notification::assertSentTo($person, SystemstatusGeaendert::class, fn ($n) => $n->titel($person) === 'Störung: VPN'
            && str_contains($n->text($person), 'Ping fehlgeschlagen'));
        Notification::assertNotSentTo($andere, SystemstatusGeaendert::class);

        $this->heartbeat(['vpn' => 'ok']);

        Notification::assertSentToTimes($person, SystemstatusGeaendert::class, 2);
        Notification::assertSentTo($person, SystemstatusGeaendert::class, fn ($n) => $n->titel($person) === 'Wieder in Betrieb: VPN');
    }

    public function test_folgefehler_werden_erst_gemeldet_wenn_die_ursache_behoben_ist(): void
    {
        Notification::fake();
        [$rolle, $person] = $this->rolleMitPerson();
        $this->monitoring->einstellungSpeichern('monitoring_rollen_benachrichtigen', json_encode([$rolle->id]));

        $this->heartbeat(['vpn' => 'ok', 'rdp' => 'ok']);
        $this->dienst('rdp')->update(['abhaengig_von_id' => $this->dienst('vpn')->id]);

        // VPN weg → RDP zwangsläufig auch: nur VPN wird gemeldet
        $this->heartbeat(['vpn' => 'fehler', 'rdp' => 'fehler']);
        Notification::assertSentTo($person, SystemstatusGeaendert::class, fn ($n) => $n->stoerungen === [['name' => 'VPN', 'meldung' => null]]);
        $this->assertFalse($this->dienst('rdp')->gemeldet);

        // VPN wieder da, RDP weiterhin gestört → jetzt wird RDP gemeldet
        $this->heartbeat(['vpn' => 'ok', 'rdp' => 'fehler']);
        Notification::assertSentTo($person, SystemstatusGeaendert::class, fn ($n) => collect($n->stoerungen)->pluck('name')->all() === ['Terminalserver (RDP)']
            && collect($n->behoben)->pluck('name')->all() === ['VPN']);

        $anzeige = $this->monitoring->anzeige()->keyBy('name');
        $this->assertSame('fehler', $anzeige['Terminalserver (RDP)']['status']);
    }

    public function test_ausbleibender_heartbeat_wird_gemeldet_ohne_folgefehler(): void
    {
        Notification::fake();
        [$rolle, $person] = $this->rolleMitPerson();
        $this->monitoring->einstellungSpeichern('monitoring_rollen_benachrichtigen', json_encode([$rolle->id]));
        $this->monitoring->einstellungSpeichern('monitoring_heartbeat_minuten', '15');

        $this->heartbeat(['vpn' => 'ok']);

        $this->travel(10)->minutes();
        $this->monitoring->pruefen();
        $this->assertSame('ok', $this->dienst('heartbeat')->status);

        $this->travel(10)->minutes();
        $this->monitoring->pruefen();

        $quelle = $this->dienst('heartbeat');
        $this->assertSame('fehler', $quelle->status);
        $this->assertStringContainsString('Kein Heartbeat seit', $quelle->meldung);
        Notification::assertSentToTimes($person, SystemstatusGeaendert::class, 1);

        // Heartbeat-Dienste gelten als unbekannt, solange die Überwachung ausgefallen ist
        $anzeige = $this->monitoring->anzeige()->keyBy('name');
        $this->assertSame('unbekannt', $anzeige['VPN']['status']);
        $this->assertSame('fehler', $anzeige['Überwachung (Heartbeat)']['status']);

        // Heartbeat kommt wieder → Behebung
        $this->heartbeat(['vpn' => 'ok']);
        Notification::assertSentTo($person, SystemstatusGeaendert::class, fn ($n) => collect($n->behoben)->pluck('name')->all() === ['Überwachung (Heartbeat)']);
    }

    public function test_webserver_gilt_erst_nach_zwei_fehlschlaegen_als_gestoert(): void
    {
        Notification::fake();
        Http::fake([
            'ok.example.org/*' => Http::response('hallo', 200),
            'kaputt.example.org/*' => Http::response('fehler', 503),
        ]);

        $gut = MonitoringDienst::create(['schluessel' => 'http-gut', 'name' => 'Gut', 'typ' => 'http', 'url' => 'https://ok.example.org/']);
        $schlecht = MonitoringDienst::create(['schluessel' => 'http-schlecht', 'name' => 'Schlecht', 'typ' => 'http', 'url' => 'https://kaputt.example.org/', 'status' => 'ok']);

        $this->monitoring->pruefen();
        $this->assertSame('ok', $gut->fresh()->status);
        $this->assertSame('ok', $schlecht->fresh()->status);
        $this->assertSame(1, $schlecht->fresh()->fehlversuche);

        $this->monitoring->pruefen();
        $this->assertSame('fehler', $schlecht->fresh()->status);
        $this->assertSame('HTTP 503', $schlecht->fresh()->meldung);
    }

    /*
    | Oberfläche
    */

    public function test_statusleiste_nur_fuer_gewaehlte_rollen(): void
    {
        [$rolle, $person] = $this->rolleMitPerson();
        $this->heartbeat(['vpn' => 'ok', 'rdp' => 'fehler']);

        $this->actingAs($person)->get(route('monitoring.statusleiste'))->assertNoContent();

        $this->monitoring->einstellungSpeichern('monitoring_rollen_sichtbar', json_encode([$rolle->id]));

        $this->actingAs($person)->get(route('monitoring.statusleiste'))
            ->assertOk()
            ->assertSee('Systemstatus')
            ->assertSee('VPN')
            ->assertSee('Terminalserver (RDP)')
            ->assertSee('gestört');

        $this->actingAs(User::factory()->create())->get(route('monitoring.statusleiste'))->assertNoContent();
    }

    public function test_dashboard_zeigt_statusleiste_oberhalb_der_karten(): void
    {
        $this->fakeAllExternalApis();
        $person = $this->actingAsWithPermission('use dashboard v2');
        $rolle = Role::create(['name' => 'Leitung', 'guard_name' => 'web']);
        $person->assignRole($rolle);
        $this->monitoring->einstellungSpeichern('monitoring_rollen_sichtbar', json_encode([$rolle->id]));
        $this->heartbeat(['vpn' => 'ok']);

        $this->get('/')->assertOk()->assertSee('mon-leiste', false)->assertSee('VPN');
    }

    public function test_einstellungen_speichern(): void
    {
        $this->actingAsWithPermission('edit settings');
        $rolle = Role::create(['name' => 'IT', 'guard_name' => 'web']);
        $this->heartbeat(['vpn' => 'ok', 'rdp' => 'ok']);
        $vpn = $this->dienst('vpn');
        $rdp = $this->dienst('rdp');

        $this->get('settings/Monitoring')->assertOk()->assertSee('Überwachte Dienste')->assertSee('api/monitoring/heartbeat');

        $this->post(route('monitoring.einstellungen.update'), [
            'rollen_sichtbar' => [$rolle->id],
            'rollen_benachrichtigen' => [$rolle->id],
            'heartbeat_minuten' => 20,
            'dienste' => [
                $vpn->id => ['name' => 'VPN (WireGuard)', 'aktiv' => '1', 'sortierung' => 1],
                $rdp->id => ['name' => 'Terminalserver', 'aktiv' => '1', 'abhaengig_von_id' => $vpn->id, 'sortierung' => 2],
            ],
        ])->assertRedirect(url('settings/Monitoring'));

        $this->assertSame([$rolle->id], $this->monitoring->rollenSichtbar());
        $this->assertSame([$rolle->id], $this->monitoring->rollenBenachrichtigen());
        $this->assertSame(20, $this->monitoring->heartbeatMinuten());
        $this->assertSame('VPN (WireGuard)', $vpn->fresh()->name);
        $this->assertSame($vpn->id, $rdp->fresh()->abhaengig_von_id);

        // Kreis VPN → RDP → VPN wird abgelehnt
        $this->post(route('monitoring.einstellungen.update'), [
            'heartbeat_minuten' => 20,
            'dienste' => [
                $vpn->id => ['name' => 'VPN', 'aktiv' => '1', 'abhaengig_von_id' => $rdp->id],
            ],
        ])->assertSessionHasErrors("dienste.{$vpn->id}.abhaengig_von_id");
    }

    public function test_webserver_anlegen_und_loeschen(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->actingAsWithPermission('edit settings');

        $this->post(route('monitoring.dienste.anlegen'), ['name' => 'Homepage', 'url' => 'ftp://example.org'])
            ->assertSessionHasErrors('url');

        $this->post(route('monitoring.dienste.anlegen'), ['name' => 'Homepage', 'url' => 'https://example.org'])
            ->assertRedirect(url('settings/Monitoring'));

        $dienst = MonitoringDienst::where('name', 'Homepage')->firstOrFail();
        $this->assertSame('http', $dienst->typ);
        $this->assertSame('ok', $dienst->status);

        $this->delete(route('monitoring.dienste.loeschen', $dienst))->assertRedirect(url('settings/Monitoring'));
        $this->assertModelMissing($dienst);
    }

    public function test_token_erzeugen_zeigt_es_einmalig(): void
    {
        $this->actingAsWithPermission('edit settings');

        $antwort = $this->post(route('monitoring.token'))->assertRedirect(url('settings/Monitoring'));
        $neu = $antwort->getSession()->get('monitoring_token');

        $this->assertNotEmpty($neu);
        $this->assertTrue($this->monitoring->tokenGueltig($neu));
        $this->assertFalse($this->monitoring->tokenGueltig($this->token));
    }

    public function test_verwaltung_braucht_edit_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('monitoring.token'))->assertForbidden();
        $this->post(route('monitoring.einstellungen.update'), ['heartbeat_minuten' => 10])->assertForbidden();
        $this->post(route('monitoring.dienste.anlegen'), ['name' => 'x', 'url' => 'https://example.org'])->assertForbidden();
    }
}
