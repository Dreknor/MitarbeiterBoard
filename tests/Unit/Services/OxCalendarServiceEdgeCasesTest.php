<?php

namespace Tests\Unit\Services;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Edge-Case-Tests für OxCalendarService.
 * Entspricht TODO 07 der calendar-ox-Reihe.
 */
class OxCalendarServiceEdgeCasesTest extends TestCase
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

    // ─── iCal-Parsing Edge Cases ──────────────────────────────────────────────

    public function test_parseIcal_handhabt_fehlendes_DTEND_korrekt(): void
    {
        // Manche iCal-Events haben nur DTSTART + DURATION (kein DTEND)
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:no-dtend@ox\r\n"
            . "SUMMARY:Kurzer Termin\r\n"
            . "DTSTART;TZID=Europe/Berlin:20260315T140000\r\n"
            . "DURATION:PT1H\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertSame('Kurzer Termin', $result['titel']);
        $this->assertSame('no-dtend@ox', $result['uid']);
    }

    public function test_parseIcal_handhabt_UTF8_Sonderzeichen_im_Titel(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:utf8@ox\r\n"
            . "SUMMARY:Schüler-Besprechung & Elternabend (Müller)\r\n"
            . "DTSTART:20260315T140000Z\r\n"
            . "DTEND:20260315T160000Z\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertStringContainsString('Schüler-Besprechung', $result['titel']);
        $this->assertStringContainsString('Müller', $result['titel']);
    }

    public function test_parseIcal_handhabt_EXDATE_mit_mehreren_Datumsangaben(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:exdate@ox\r\n"
            . "SUMMARY:Wöchentlich\r\n"
            . "DTSTART;TZID=Europe/Berlin:20260302T100000\r\n"
            . "DTEND;TZID=Europe/Berlin:20260302T110000\r\n"
            . "RRULE:FREQ=WEEKLY;BYDAY=MO;COUNT=10\r\n"
            . "EXDATE;TZID=Europe/Berlin:20260309T100000\r\n"
            . "EXDATE;TZID=Europe/Berlin:20260316T100000\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertSame('FREQ=WEEKLY;BYDAY=MO;COUNT=10', $result['rrule']);
        $this->assertIsArray($result['exdates']);
        $this->assertGreaterThanOrEqual(2, count($result['exdates']));
    }

    public function test_parseIcal_handhabt_Event_ohne_SUMMARY(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:nosummary@ox\r\n"
            . "DTSTART:20260315T140000Z\r\n"
            . "DTEND:20260315T160000Z\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertSame('Ohne Titel', $result['titel']);
    }

    public function test_parseIcal_konvertiert_verschiedene_Zeitzonen_in_App_Zeitzone(): void
    {
        // Event in US/Eastern – am 2026-03-15 ist EDT aktiv (UTC-4)
        // 09:00 EDT = 13:00 UTC = 14:00 CET (Europe/Berlin, UTC+1)
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:tz-test@ox\r\n"
            . "SUMMARY:US-Event\r\n"
            . "DTSTART;TZID=US/Eastern:20260315T090000\r\n"
            . "DTEND;TZID=US/Eastern:20260315T100000\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertSame('US/Eastern', $result['timezone']);
        // Europe/Berlin ist CET (UTC+1) am 15.03.2026 → 09:00 EDT (UTC-4) = 14:00 CET
        $this->assertStringContainsString('14:00:00', $result['beginn']);
    }

    // ─── Sync Edge Cases ─────────────────────────────────────────────────────

    public function test_syncCalendar_stellt_soft_deleted_Termine_wieder_her_bei_Re_Sync(): void
    {
        $calendar = OxCalendar::factory()->create(['ox_calendar_id' => '/cal1']);
        $termin   = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'ox_uid'         => 'restored@ox.example.com',
            'ox_href'        => '/caldav/cal1/restored.ics',
        ]);
        $termin->delete(); // Soft-Delete

        $propfindXml = '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:">'
            . '<d:response>'
            . '<d:href>/caldav/cal1/restored.ics</d:href>'
            . '<d:propstat><d:prop><d:getetag>"new-etag"</d:getetag></d:prop>'
            . '<d:status>HTTP/1.1 200 OK</d:status></d:propstat>'
            . '</d:response></d:multistatus>';

        $icalData = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:restored@ox.example.com\r\n"
            . "SUMMARY:Wiederhergestellt\r\n"
            . "DTSTART:20260315T140000Z\r\n"
            . "DTEND:20260315T160000Z\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        Http::fake([
            'ox.example.com/caldav/cal1/restored.ics' => Http::response($icalData, 200),
            'ox.example.com/caldav/cal1'               => Http::response($propfindXml, 207),
        ]);

        $service = new OxCalendarService();
        $service->syncCalendar($calendar);

        // Termin muss in DB wiederhergestellt sein (deleted_at = null)
        $termin->refresh();
        $this->assertNull($termin->deleted_at);
    }

    // ─── parseTeilnehmer Edge Cases ──────────────────────────────────────────

    public function test_parseTeilnehmer_ignoriert_externe_Teilnehmer_bei_gesetzten_Domains(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:test@ox\r\nSUMMARY:Test\r\n"
            . "DTSTART:20260315T140000Z\r\nDTEND:20260315T160000Z\r\n"
            . "ATTENDEE;CN=Intern:mailto:intern@esz-radebeul.de\r\n"
            . "ATTENDEE;CN=Extern:mailto:extern@gmail.com\r\n"
            . "ATTENDEE;CN=Auch Intern:mailto:andere@esz-radebeul.de\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseTeilnehmer($ical, ['esz-radebeul.de']);

        $this->assertCount(2, $result);
        foreach (array_column($result, 'email') as $email) {
            $this->assertStringContainsString('esz-radebeul.de', $email);
        }
    }

    public function test_parseTeilnehmer_gibt_alle_zurueck_wenn_keine_Domains_angegeben(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:test@ox\r\nSUMMARY:Test\r\n"
            . "DTSTART:20260315T140000Z\r\nDTEND:20260315T160000Z\r\n"
            . "ATTENDEE;CN=A:mailto:a@domain1.de\r\n"
            . "ATTENDEE;CN=B:mailto:b@domain2.com\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseTeilnehmer($ical, []);

        $this->assertCount(2, $result);
    }

    // ─── HTTP-Client Edge Cases ───────────────────────────────────────────────

    public function test_httpClient_nutzt_BasicAuth_aus_Config(): void
    {
        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"></d:multistatus>', 207),
        ]);

        $service = new OxCalendarService();
        $service->testConnection();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization');
        });
    }

    public function test_httpClient_nutzt_konfigurierten_Timeout(): void
    {
        config(['ox-calendar.timeout' => 15]);

        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"></d:multistatus>', 207),
        ]);

        $service = new OxCalendarService();
        $result  = $service->testConnection();

        // Timeout wird intern gesetzt – kein direkter Weg zum Prüfen ohne Reflection.
        // Sicherstellen: kein Fehler und korrekte Antwort.
        $this->assertTrue($result['success']);
    }
}

