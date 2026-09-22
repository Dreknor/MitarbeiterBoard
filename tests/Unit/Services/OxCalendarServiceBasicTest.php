<?php

namespace Tests\Unit\Services;

use App\Services\OxCalendarService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit-Tests für OxCalendarService – Grundstruktur & CalDAV-Lesen
 * Entspricht TODO 04 der calendar-ox-Reihe.
 */
class OxCalendarServiceBasicTest extends TestCase
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

    // ─── isEnabled ───────────────────────────────────────────────────────────

    public function test_isEnabled_gibt_true_zurueck_wenn_konfiguriert(): void
    {
        $service = new OxCalendarService();
        $this->assertTrue($service->isEnabled());
    }

    public function test_isEnabled_gibt_false_zurueck_wenn_URL_fehlt(): void
    {
        config(['ox-calendar.url' => '']);
        $service = new OxCalendarService();
        $this->assertFalse($service->isEnabled());
    }

    public function test_isEnabled_gibt_false_zurueck_wenn_deaktiviert(): void
    {
        config(['ox-calendar.enabled' => false]);
        $service = new OxCalendarService();
        $this->assertFalse($service->isEnabled());
    }

    // ─── testConnection ───────────────────────────────────────────────────────

    public function test_testConnection_gibt_Erfolg_bei_gueltiger_Antwort(): void
    {
        Http::fake([
            'ox.example.com/*' => Http::response(
                '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:">'
                . '<d:response><d:href>/caldav</d:href>'
                . '<d:propstat><d:prop><d:displayname>CalDAV</d:displayname></d:prop>'
                . '<d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
                . '</d:multistatus>',
                207
            ),
        ]);

        $service = new OxCalendarService();
        $result  = $service->testConnection();

        $this->assertTrue($result['success']);
    }

    public function test_testConnection_gibt_Fehler_bei_nicht_erreichbarem_Server(): void
    {
        Http::fake([
            'ox.example.com/*' => Http::response('', 500),
        ]);

        $service = new OxCalendarService();
        $result  = $service->testConnection();

        $this->assertFalse($result['success']);
    }

    // ─── parseIcal ───────────────────────────────────────────────────────────

    public function test_parseIcal_extrahiert_Standard_Event_korrekt(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:test-123@ox.example.com\r\n"
            . "SUMMARY:Gesamtkonferenz\r\n"
            . "DESCRIPTION:Tagesordnung folgt\r\n"
            . "LOCATION:Aula\r\n"
            . "DTSTART;TZID=Europe/Berlin:20260315T140000\r\n"
            . "DTEND;TZID=Europe/Berlin:20260315T160000\r\n"
            . "STATUS:CONFIRMED\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertSame('Gesamtkonferenz', $result['titel']);
        $this->assertSame('Tagesordnung folgt', $result['beschreibung']);
        $this->assertSame('Aula', $result['ort']);
        $this->assertSame('Europe/Berlin', $result['timezone']);
        $this->assertFalse($result['ganztaegig']);
        $this->assertSame('CONFIRMED', $result['status']);
        $this->assertSame('test-123@ox.example.com', $result['uid']);
    }

    public function test_parseIcal_erkennt_ganztaegige_Termine(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:allday-123@ox.example.com\r\n"
            . "SUMMARY:Fortbildungstag\r\n"
            . "DTSTART;VALUE=DATE:20260318\r\n"
            . "DTEND;VALUE=DATE:20260319\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertTrue($result['ganztaegig']);
        $this->assertSame('Fortbildungstag', $result['titel']);
    }

    public function test_parseIcal_extrahiert_RRULE(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:rrule-123@ox.example.com\r\n"
            . "SUMMARY:Wöchentliche Besprechung\r\n"
            . "DTSTART;TZID=Europe/Berlin:20260302T100000\r\n"
            . "DTEND;TZID=Europe/Berlin:20260302T110000\r\n"
            . "RRULE:FREQ=WEEKLY;BYDAY=MO;COUNT=10\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertSame('FREQ=WEEKLY;BYDAY=MO;COUNT=10', $result['rrule']);
    }

    public function test_parseIcal_wirft_Exception_bei_fehlendem_VEVENT(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR";

        $this->expectException(\RuntimeException::class);

        $service = new OxCalendarService();
        $service->parseIcal($ical);
    }

    // ─── parseTeilnehmer ─────────────────────────────────────────────────────

    public function test_parseTeilnehmer_extrahiert_interne_Teilnehmer(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:test@ox\r\n"
            . "SUMMARY:Test\r\n"
            . "DTSTART:20260315T140000Z\r\n"
            . "DTEND:20260315T160000Z\r\n"
            . "ATTENDEE;CN=Max Mustermann;PARTSTAT=ACCEPTED:mailto:max@schule.de\r\n"
            . "ATTENDEE;CN=Extern Person;PARTSTAT=TENTATIVE:mailto:extern@gmail.com\r\n"
            . "ATTENDEE;CN=Erika Muster;PARTSTAT=NEEDS-ACTION:mailto:erika@schule.de\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseTeilnehmer($ical, ['schule.de']);

        $this->assertCount(2, $result);
        $this->assertSame('max@schule.de', $result[0]['email']);
        $this->assertSame('Max Mustermann', $result[0]['name']);
        $this->assertSame('ACCEPTED', $result[0]['status']);
        $this->assertSame('erika@schule.de', $result[1]['email']);
    }

    public function test_parseTeilnehmer_gibt_leeres_Array_bei_fehlenden_Attendees(): void
    {
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:test@ox\r\n"
            . "SUMMARY:Test\r\n"
            . "DTSTART:20260315T140000Z\r\n"
            . "DTEND:20260315T160000Z\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $this->assertEmpty($service->parseTeilnehmer($ical));
    }
}

