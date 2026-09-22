<?php

namespace Tests\Unit\Services;

use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit-Tests für OxCalendarService – Sync-Logik & Fallback-Kette
 * Entspricht TODO 05 der calendar-ox-Reihe.
 */
class OxCalendarServiceSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ox-calendar.url'        => 'https://ox.example.com/caldav',
            'ox-calendar.username'   => 'testuser',
            'ox-calendar.password'   => 'testpass',
            'ox-calendar.enabled'    => true,
            'ox-calendar.verify_ssl' => true,
            'ox-calendar.timeout'    => 30,
        ]);
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function sampleIcal(string $uid = 'test-123', string $summary = 'Test-Termin'): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:{$uid}@ox.example.com\r\n"
            . "SUMMARY:{$summary}\r\n"
            . "DTSTART;TZID=Europe/Berlin:20260315T140000\r\n"
            . "DTEND;TZID=Europe/Berlin:20260315T160000\r\n"
            . "STATUS:CONFIRMED\r\n"
            . "ATTENDEE;CN=Max Mustermann;PARTSTAT=ACCEPTED:mailto:max@schule.de\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";
    }

    private function propfindResponse(array $events = [], ?string $syncToken = 'new-sync-token'): string
    {
        $responses = '';
        foreach ($events as $event) {
            $responses .= '<d:response>'
                . '<d:href>' . $event['href'] . '</d:href>'
                . '<d:propstat>'
                . '<d:prop><d:getetag>"' . ($event['etag'] ?? 'etag-123') . '"</d:getetag></d:prop>'
                . '<d:status>HTTP/1.1 ' . ($event['status'] ?? '200 OK') . '</d:status>'
                . '</d:propstat>'
                . '</d:response>';
        }
        $token = $syncToken ? "<d:sync-token>{$syncToken}</d:sync-token>" : '';
        return '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:">' . $responses . $token . '</d:multistatus>';
    }

    // ─── syncCalendar ─────────────────────────────────────────────────────────

    public function test_syncCalendar_erstellt_neue_Termine_aus_OX(): void
    {
        $calendar = OxCalendar::factory()->create(['ox_calendar_id' => '/caldav/cal1']);

        Http::fake([
            'ox.example.com/caldav/caldav/cal1' => Http::response(
                $this->propfindResponse([
                    ['href' => '/caldav/cal1/event1.ics', 'etag' => 'etag-1'],
                ]),
                207
            ),
            'ox.example.com/caldav/caldav/cal1/event1.ics' => Http::response(
                $this->sampleIcal('event1', 'Konferenz'),
                200
            ),
        ]);

        $service = new OxCalendarService();
        $result  = $service->syncCalendar($calendar);

        $this->assertNotEmpty($result['method']);
        $this->assertGreaterThanOrEqual(0, OxTermin::where('ox_calendar_id', $calendar->id)->count());
        $this->assertGreaterThanOrEqual(1, OxSyncLog::where('ox_calendar_id', $calendar->id)->count());
    }

    public function test_syncCalendar_aktualisiert_letzte_synchronisation(): void
    {
        $calendar = OxCalendar::factory()->create(['letzte_synchronisation' => null]);

        Http::fake([
            '*' => Http::response($this->propfindResponse([]), 207),
        ]);

        $service = new OxCalendarService();
        $service->syncCalendar($calendar);

        $calendar->refresh();
        $this->assertNotNull($calendar->letzte_synchronisation);
    }

    public function test_syncCalendar_loggt_sync_start_und_sync_complete(): void
    {
        $calendar = OxCalendar::factory()->create();

        Http::fake([
            '*' => Http::response($this->propfindResponse([]), 207),
        ]);

        $service = new OxCalendarService();
        $service->syncCalendar($calendar);

        $this->assertTrue(
            OxSyncLog::where('ox_calendar_id', $calendar->id)
                ->where('aktion', 'sync_start')
                ->exists()
        );
        $this->assertTrue(
            OxSyncLog::where('ox_calendar_id', $calendar->id)
                ->where('aktion', 'sync_complete')
                ->exists()
        );
    }

    public function test_syncCalendar_loggt_Fehler_bei_nicht_erreichbarem_Server(): void
    {
        $calendar = OxCalendar::factory()->create();

        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $service = new OxCalendarService();
        $result  = $service->syncCalendar($calendar);

        $this->assertGreaterThan(0, $result['errors']);
        $this->assertTrue(OxSyncLog::where('aktion', 'error')->exists());
    }

    // ─── syncAll ─────────────────────────────────────────────────────────────

    public function test_syncAll_synchronisiert_nur_sichtbare_Kalender(): void
    {
        OxCalendar::factory()->create(['sichtbar' => true, 'name' => 'Sichtbar']);
        OxCalendar::factory()->unsichtbar()->create(['name' => 'Unsichtbar']);

        Http::fake([
            '*' => Http::response($this->propfindResponse([]), 207),
        ]);

        $service = new OxCalendarService();
        $results = $service->syncAll();

        $this->assertArrayHasKey('Sichtbar', $results);
        $this->assertArrayNotHasKey('Unsichtbar', $results);
    }

    public function test_syncAll_gibt_leeres_Array_wenn_deaktiviert(): void
    {
        config(['ox-calendar.enabled' => false]);

        $service = new OxCalendarService();
        $this->assertEmpty($service->syncAll());
    }

    // ─── syncTeilnehmer (geschützte Methode per Reflection) ──────────────────

    public function test_syncTeilnehmer_erstellt_Teilnehmer_Eintraege(): void
    {
        $termin = OxTermin::factory()->create();

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('syncTeilnehmer');
        $method->setAccessible(true);
        $method->invoke($service, $termin, $this->sampleIcal());

        $this->assertSame(1, $termin->teilnehmer()->count());
        $this->assertSame('max@schule.de', $termin->teilnehmer()->first()->email);
    }

    // ─── upsertTermin (geschützte Methode per Reflection) ────────────────────

    public function test_upsertTermin_erstellt_neuen_Termin(): void
    {
        $calendar = OxCalendar::factory()->create();

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('upsertTermin');
        $method->setAccessible(true);
        $method->invoke($service, $calendar, ['href' => '/event.ics', 'etag' => '"etag-1"'], $this->sampleIcal('new-uid'));

        $this->assertTrue(
            OxTermin::where('ox_uid', 'new-uid@ox.example.com')->exists()
        );
    }

    public function test_upsertTermin_aktualisiert_bestehenden_Termin(): void
    {
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'ox_uid'         => 'existing-uid@ox.example.com',
            'titel'          => 'Alt',
        ]);

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('upsertTermin');
        $method->setAccessible(true);
        $method->invoke($service, $calendar, ['href' => '/event.ics', 'etag' => '"new-etag"'], $this->sampleIcal('existing-uid', 'Neu'));

        $termin = OxTermin::where('ox_uid', 'existing-uid@ox.example.com')->first();
        $this->assertSame('Neu', $termin->titel);
    }

    // ─── softDeleteTerminByHref (geschützte Methode per Reflection) ──────────

    public function test_softDeleteTerminByHref_soft_deleted_Termin(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin   = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'ox_href'        => '/caldav/cal1/deleted-event.ics',
        ]);

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('softDeleteTerminByHref');
        $method->setAccessible(true);
        $method->invoke($service, $calendar, '/caldav/cal1/deleted-event.ics');

        $this->assertNull(OxTermin::find($termin->id));
        $this->assertNotNull(OxTermin::withTrashed()->find($termin->id));
    }
}

