<?php

namespace Tests\Unit\Services;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Tests\TestCase;

/**
 * iCal-Roundtrip-Tests – stellt sicher, dass raw_ical und
 * ganztägige Zeiten korrekt in der DB gespeichert werden.
 * Entspricht TODO 07 der calendar-ox-Reihe.
 */
class OxCalendarServiceIcalRoundtripTest extends TestCase
{
    /** Originale PHP-Zeitzone, wird in tearDown wiederhergestellt. */
    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        // UTC erzwingen, damit ganztägige Termine deterministisch sind
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        config([
            'ox-calendar.url'      => 'https://ox.example.com/caldav',
            'ox-calendar.username' => 'testuser',
            'ox-calendar.password' => 'testpass',
            'ox-calendar.enabled'  => true,
        ]);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
        parent::tearDown();
    }

    public function test_raw_ical_wird_beim_Upsert_gespeichert(): void
    {
        $calendar = OxCalendar::factory()->create();
        $ical     = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:roundtrip@ox\r\n"
            . "SUMMARY:Test\r\n"
            . "DTSTART:20260315T140000Z\r\n"
            . "DTEND:20260315T160000Z\r\n"
            . "X-CUSTOM-PROP:BehaltenWerden\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('upsertTermin');
        $method->setAccessible(true);
        $method->invoke($service, $calendar, ['href' => '/event.ics', 'etag' => '"e1"'], $ical);

        $termin = OxTermin::where('ox_uid', 'roundtrip@ox')->first();
        $this->assertStringContainsString('X-CUSTOM-PROP:BehaltenWerden', $termin->raw_ical);
    }

    public function test_Ganztaegiger_Termin_hat_korrekte_Zeiten_in_DB(): void
    {
        // DATE 20260318 → 2026-03-18 00:00:00 (App-Zeitzone, kein Uhrzeit-Anteil bei ganztägig)
        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:allday@ox\r\n"
            . "SUMMARY:Ganztägig\r\n"
            . "DTSTART;VALUE=DATE:20260318\r\n"
            . "DTEND;VALUE=DATE:20260319\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service = new OxCalendarService();
        $result  = $service->parseIcal($ical);

        $this->assertTrue($result['ganztaegig']);
        $this->assertStringContainsString('2026-03-18', $result['beginn']);
        $this->assertStringContainsString('2026-03-19', $result['ende']);
    }
}

