<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-Tests für CalendarAdminController::health() (TODO 26).
 */
class CalendarHealthCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ox-calendar.url'      => 'https://ox.example.com/caldav',
            'ox-calendar.username' => 'testuser',
            'ox-calendar.password' => 'testpass',
            'ox-calendar.enabled'  => true,
        ]);
    }

    public function test_health_endpoint_erfordert_authentifizierung(): void
    {
        $this->getJson(route('calendar.admin.health'))
            ->assertUnauthorized();
    }

    public function test_health_erfordert_manage_calendar_permission(): void
    {
        $this->actingAsWithPermission('view calendar');

        $this->getJson(route('calendar.admin.health'))
            ->assertForbidden();
    }

    public function test_health_endpoint_liefert_json_mit_korrekter_struktur(): void
    {
        $admin = $this->actingAsWithPermission('manage calendar');

        Http::fake(['*' => Http::response('<multistatus/>', 207, ['DAV' => '1, calendar-access'])]);

        $this->actingAs($admin)
            ->getJson(route('calendar.admin.health'))
            ->assertOk()
            ->assertJsonStructure([
                'modul_aktiviert',
                'ox_erreichbar',
                'ox_status',
                'ox_message',
                'letzter_sync',
                'sync_alter_minuten',
                'sync_veraltet',
                'fehler_24h',
                'aufeinanderfolgende_fehler',
                'kalender_aktiv',
                'kalender_gesamt',
                'termine_gesamt',
                'status',
                'timestamp',
            ])
            ->assertJson(['status' => 'healthy']);
    }

    public function test_health_gibt_503_bei_aufeinanderfolgenden_fehlern(): void
    {
        $admin = $this->actingAsWithPermission('manage calendar');
        $calendar = OxCalendar::factory()->create();

        // 3 aufeinanderfolgende Fehler simulieren
        for ($i = 0; $i < 3; $i++) {
            OxSyncLog::factory()->create([
                'ox_calendar_id' => $calendar->id,
                'aktion'         => 'error',
                'created_at'     => now()->subMinutes($i),
            ]);
        }

        Http::fake(['*' => Http::response('<multistatus/>', 207, ['DAV' => '1'])]);

        $this->actingAs($admin)
            ->getJson(route('calendar.admin.health'))
            ->assertStatus(503)
            ->assertJson([
                'status'                     => 'unhealthy',
                'aufeinanderfolgende_fehler' => 3,
            ]);
    }

    public function test_health_ist_unhealthy_wenn_modul_deaktiviert(): void
    {
        config(['ox-calendar.enabled' => false]);
        $admin = $this->actingAsWithPermission('manage calendar');

        Http::fake();

        $this->actingAs($admin)
            ->getJson(route('calendar.admin.health'))
            ->assertStatus(503)
            ->assertJson([
                'modul_aktiviert' => false,
                'status'          => 'unhealthy',
            ]);
    }

    public function test_health_meldet_sync_veraltet_wenn_letzter_sync_aelter_als_1_stunde(): void
    {
        $admin = $this->actingAsWithPermission('manage calendar');
        $calendar = OxCalendar::factory()->create();

        // Letzter erfolgreicher Sync vor 2 Stunden
        OxSyncLog::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'sync_complete',
            'created_at'     => now()->subHours(2),
        ]);

        Http::fake(['*' => Http::response('<multistatus/>', 207, ['DAV' => '1'])]);

        $this->actingAs($admin)
            ->getJson(route('calendar.admin.health'))
            ->assertJson(['sync_veraltet' => true]);
    }

    public function test_health_sync_nicht_veraltet_bei_aktuellem_sync(): void
    {
        $admin = $this->actingAsWithPermission('manage calendar');
        $calendar = OxCalendar::factory()->create();

        // Letzter Sync vor 5 Minuten
        OxSyncLog::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'sync_complete',
            'created_at'     => now()->subMinutes(5),
        ]);

        Http::fake(['*' => Http::response('<multistatus/>', 207, ['DAV' => '1'])]);

        $this->actingAs($admin)
            ->getJson(route('calendar.admin.health'))
            ->assertOk()
            ->assertJson([
                'sync_veraltet' => false,
                'status'        => 'healthy',
            ]);
    }

    public function test_health_zaehlt_fehler_der_letzten_24h(): void
    {
        $admin = $this->actingAsWithPermission('manage calendar');
        $calendar = OxCalendar::factory()->create();

        // 2 Fehler in den letzten 24h, danach 1 erfolgreicher Sync
        OxSyncLog::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'error',
            'created_at'     => now()->subHours(3),
        ]);
        OxSyncLog::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'error',
            'created_at'     => now()->subHours(1),
        ]);
        // Erfolgreicher Sync → Reset der aufeinanderfolgenden Fehler
        OxSyncLog::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'aktion'         => 'sync_complete',
            'created_at'     => now()->subMinutes(10),
        ]);

        Http::fake(['*' => Http::response('<multistatus/>', 207, ['DAV' => '1'])]);

        $this->actingAs($admin)
            ->getJson(route('calendar.admin.health'))
            ->assertOk()
            ->assertJson([
                'fehler_24h'                 => 2,
                'aufeinanderfolgende_fehler' => 0,
                'status'                     => 'healthy',
            ]);
    }

    public function test_health_liefert_korrekte_db_statistiken(): void
    {
        $admin = $this->actingAsWithPermission('manage calendar');

        // 2 sichtbare, 1 nicht sichtbarer Kalender
        $sichtbar1 = OxCalendar::factory()->create(['sichtbar' => true]);
        OxCalendar::factory()->create(['sichtbar' => true]);
        OxCalendar::factory()->unsichtbar()->create();
        OxTermin::factory()->count(5)->create([
            'ox_calendar_id' => $sichtbar1->id,
        ]);

        Http::fake(['*' => Http::response('<multistatus/>', 207, ['DAV' => '1'])]);

        $response = $this->actingAs($admin)
            ->getJson(route('calendar.admin.health'))
            ->assertOk();

        $data = $response->json();
        $this->assertEquals(2, $data['kalender_aktiv']);
        $this->assertEquals(3, $data['kalender_gesamt']); // 2 sichtbar + 1 unsichtbar
        $this->assertEquals(5, $data['termine_gesamt']);
    }
}

