<?php

namespace Tests\Unit\Services;

use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit-Tests für OxCalendarService – CRUD-Operationen & iCal-Erstellung
 * Entspricht TODO 14 der calendar-ox-Reihe.
 */
class OxCalendarServiceCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ox-calendar.url'        => 'https://ox.example.com/caldav',
            'ox-calendar.username'   => 'testuser',
            'ox-calendar.password'   => 'testpass',
            'ox-calendar.enabled'    => true,
            'ox-calendar.verify_ssl' => false,
            'ox-calendar.timeout'    => 30,
        ]);
    }

    // ─── createTermin ────────────────────────────────────────────────────────

    public function test_createTermin_erstellt_Termin_in_OX_und_lokal(): void
    {
        $user     = $this->actingAsWithPermission('create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create(['ox_calendar_id' => '/cal1']);

        Http::fake([
            '*' => Http::response('', 201, ['ETag' => '"new-etag-123"']),
        ]);

        $service = new OxCalendarService();
        $termin  = $service->createTermin($calendar, [
            'titel'        => 'Neue Konferenz',
            'beschreibung' => 'Tagesordnung folgt',
            'ort'          => 'Aula',
            'beginn'       => '2026-03-20 14:00:00',
            'ende'         => '2026-03-20 16:00:00',
        ]);

        $this->assertInstanceOf(OxTermin::class, $termin);
        $this->assertSame('Neue Konferenz', $termin->titel);
        $this->assertSame('"new-etag-123"', $termin->ox_etag);
        $this->assertSame($user->id, $termin->erstellt_von);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $termin->raw_ical);

        // Audit-Log prüfen
        $this->assertTrue(OxSyncLog::where('aktion', 'create')->exists());
    }

    public function test_createTermin_wirft_Exception_bei_CalDAV_Fehler(): void
    {
        $this->actingAsWithPermission('create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $service = new OxCalendarService();

        $this->expectException(\RuntimeException::class);
        $service->createTermin($calendar, [
            'titel'  => 'Test',
            'beginn' => '2026-03-20 14:00:00',
            'ende'   => '2026-03-20 16:00:00',
        ]);

        // Kein lokaler Eintrag erstellt
        $this->assertSame(0, OxTermin::count());
    }

    // ─── updateTermin ────────────────────────────────────────────────────────

    public function test_updateTermin_aktualisiert_Termin_in_OX_und_lokal(): void
    {
        $this->actingAsWithPermission('edit calendar events');
        $termin = OxTermin::factory()->create([
            'titel'    => 'Alt',
            'ox_etag'  => '"old-etag"',
            'ox_href'  => '/cal1/event.ics',
        ]);

        Http::fake([
            '*' => Http::response('', 200, ['ETag' => '"updated-etag"']),
        ]);

        $service = new OxCalendarService();
        $updated = $service->updateTermin($termin, [
            'titel'        => 'Neu',
            'beschreibung' => 'Aktualisiert',
            'beginn'       => '2026-03-20 15:00:00',
            'ende'         => '2026-03-20 17:00:00',
        ]);

        $this->assertSame('Neu', $updated->titel);
        $this->assertSame('"updated-etag"', $updated->ox_etag);
    }

    public function test_updateTermin_wirft_Exception_bei_ETag_Mismatch_412(): void
    {
        $this->actingAsWithPermission('edit calendar events');
        $termin = OxTermin::factory()->create(['ox_etag' => '"old"']);

        Http::fake([
            '*' => Http::response('', 412),
        ]);

        $service = new OxCalendarService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/zwischenzeitlich/');

        $service->updateTermin($termin, [
            'titel'  => 'Test',
            'beginn' => '2026-03-20 14:00:00',
            'ende'   => '2026-03-20 16:00:00',
        ]);
    }

    // ─── deleteTermin ────────────────────────────────────────────────────────

    public function test_deleteTermin_loescht_in_OX_und_soft_deleted_lokal(): void
    {
        $this->actingAsWithPermission('edit calendar events');
        $termin = OxTermin::factory()->create(['ox_href' => '/cal1/event.ics']);

        Http::fake([
            '*' => Http::response('', 204),
        ]);

        $service = new OxCalendarService();
        $service->deleteTermin($termin);

        $this->assertNull(OxTermin::find($termin->id));
        $this->assertNotNull(OxTermin::withTrashed()->find($termin->id));
        $this->assertTrue(OxSyncLog::where('aktion', 'delete')->exists());
    }

    // ─── buildRrule ──────────────────────────────────────────────────────────

    public function test_buildRrule_generiert_korrekten_RRULE_String(): void
    {
        $service = new OxCalendarService();

        // Wöchentlich, Montag und Mittwoch, 10 Mal
        $rrule = $service->buildRrule([
            'frequency' => 'WEEKLY',
            'byDay'     => ['MO', 'WE'],
            'count'     => 10,
        ]);
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO,WE;COUNT=10', $rrule);

        // Monatlich, bis Datum
        $rrule = $service->buildRrule([
            'frequency' => 'MONTHLY',
            'until'     => '2026-12-31',
        ]);
        $this->assertStringContainsString('FREQ=MONTHLY', $rrule);
        $this->assertStringContainsString('UNTIL=', $rrule);

        // Täglich, alle 2 Tage
        $rrule = $service->buildRrule([
            'frequency' => 'DAILY',
            'interval'  => 2,
        ]);
        $this->assertSame('FREQ=DAILY;INTERVAL=2', $rrule);
    }

    // ─── buildIcal ───────────────────────────────────────────────────────────

    public function test_buildIcal_erzeugt_valides_iCal(): void
    {
        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('buildIcal');
        $method->setAccessible(true);

        $ical = $method->invoke($service, [
            'uid'          => 'test-uid@mb',
            'titel'        => 'Testtermin',
            'beschreibung' => 'Beschreibung',
            'ort'          => 'Aula',
            'beginn'       => '2026-03-20 14:00:00',
            'ende'         => '2026-03-20 16:00:00',
        ]);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ical);
        $this->assertStringContainsString('BEGIN:VEVENT', $ical);
        $this->assertStringContainsString('SUMMARY:Testtermin', $ical);
        $this->assertStringContainsString('DESCRIPTION:Beschreibung', $ical);
        $this->assertStringContainsString('LOCATION:Aula', $ical);
        $this->assertStringContainsString('UID:test-uid@mb', $ical);
        $this->assertStringContainsString('END:VEVENT', $ical);
        $this->assertStringContainsString('END:VCALENDAR', $ical);
    }

    public function test_buildIcal_mit_RRULE(): void
    {
        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('buildIcal');
        $method->setAccessible(true);

        $ical = $method->invoke($service, [
            'uid'    => 'rrule@mb',
            'titel'  => 'Wöchentlich',
            'beginn' => '2026-03-20 14:00:00',
            'ende'   => '2026-03-20 16:00:00',
            'rrule'  => 'FREQ=WEEKLY;BYDAY=FR;COUNT=8',
        ]);

        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=FR;COUNT=8', $ical);
    }

    // ─── updateExistingIcal ──────────────────────────────────────────────────

    public function test_updateExistingIcal_behaelt_X_Properties(): void
    {
        $rawIcal = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\n"
            . "UID:keep-x-props@ox\r\nSUMMARY:Original\r\n"
            . "DTSTART;TZID=Europe/Berlin:20260315T140000\r\n"
            . "DTEND;TZID=Europe/Berlin:20260315T160000\r\n"
            . "X-CUSTOM-FIELD:KeepMe\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR";

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('updateExistingIcal');
        $method->setAccessible(true);

        $updatedIcal = $method->invoke($service, $rawIcal, [
            'titel'  => 'Aktualisiert',
            'beginn' => '2026-03-20 15:00:00',
            'ende'   => '2026-03-20 17:00:00',
        ]);

        $this->assertStringContainsString('SUMMARY:Aktualisiert', $updatedIcal);
        $this->assertStringContainsString('X-CUSTOM-FIELD:KeepMe', $updatedIcal);
    }

    // ─── CalDAV PUT Content-Type ──────────────────────────────────────────────

    public function test_CalDAV_PUT_sendet_korrekten_Content_Type(): void
    {
        $this->actingAsWithPermission('create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake([
            '*' => Http::response('', 201, ['ETag' => '"e1"']),
        ]);

        $service = new OxCalendarService();
        $service->createTermin($calendar, [
            'titel'  => 'Test',
            'beginn' => '2026-03-20 14:00:00',
            'ende'   => '2026-03-20 16:00:00',
        ]);

        Http::assertSent(function ($request) {
            $contentType = $request->header('Content-Type')[0] ?? '';
            return str_contains($contentType, 'text/calendar');
        });
    }
}

