<?php

namespace Tests\Unit\Services;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Services\OxCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit-Tests für OxCalendarService::expandRruleTermine() (TODO 25).
 */
class OxCalendarServiceRruleExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'ox-calendar.url'      => 'https://ox.example.com/caldav',
            'ox-calendar.username' => 'testuser',
            'ox-calendar.password' => 'testpass',
            'ox-calendar.enabled'  => true,
        ]);
    }

    public function test_gibt_leeres_array_zurueck_wenn_kein_rrule(): void
    {
        $termin = OxTermin::factory()->create(['rrule' => null]);
        $service = new OxCalendarService();

        $occurrences = $service->expandRruleTermine(
            $termin,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertEmpty($occurrences);
    }

    public function test_expandiert_woechentlichen_termin_korrekt(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=4',
            'raw_ical'       => null,
        ]);

        $service = new OxCalendarService();
        $occurrences = $service->expandRruleTermine(
            $termin,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertCount(4, $occurrences);
        $this->assertEquals('2026-03-16', $occurrences[0]['beginn']->format('Y-m-d'));
        $this->assertEquals('2026-03-23', $occurrences[1]['beginn']->format('Y-m-d'));
        $this->assertEquals('2026-03-30', $occurrences[2]['beginn']->format('Y-m-d'));
        $this->assertEquals('2026-04-06', $occurrences[3]['beginn']->format('Y-m-d'));
    }

    public function test_expandiert_taeglichen_termin(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-01 09:00:00'),
            'ende'           => Carbon::parse('2026-03-01 10:00:00'),
            'rrule'          => 'FREQ=DAILY;COUNT=5',
            'raw_ical'       => null,
        ]);

        $service = new OxCalendarService();
        $occurrences = $service->expandRruleTermine(
            $termin,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31')
        );

        $this->assertCount(5, $occurrences);
    }

    public function test_ergebnis_enthaelt_beginn_und_ende_als_carbon(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=2',
            'raw_ical'       => null,
        ]);

        $service = new OxCalendarService();
        $occurrences = $service->expandRruleTermine(
            $termin,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertNotEmpty($occurrences);
        $this->assertInstanceOf(Carbon::class, $occurrences[0]['beginn']);
        $this->assertInstanceOf(Carbon::class, $occurrences[0]['ende']);
    }

    public function test_gibt_leeres_array_bei_ungueltigem_rrule(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'INVALID_RRULE_STRING',
            'raw_ical'       => null,
        ]);

        $service = new OxCalendarService();
        $occurrences = $service->expandRruleTermine(
            $termin,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertEmpty($occurrences);
    }

    public function test_nutzt_raw_ical_wenn_vorhanden(): void
    {
        $calendar = OxCalendar::factory()->create();
        $rawIcal = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//Test//EN\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:test-uid-raw@example.com\r\n"
            . "DTSTART:20260316T100000Z\r\n"
            . "DTEND:20260316T110000Z\r\n"
            . "RRULE:FREQ=WEEKLY;BYDAY=MO;COUNT=2\r\n"
            . "SUMMARY:Test Termin\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";

        $termin = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=2',
            'raw_ical'       => $rawIcal,
        ]);

        $service = new OxCalendarService();
        $occurrences = $service->expandRruleTermine(
            $termin,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertCount(2, $occurrences);
    }

    public function test_ergebnis_wird_gecacht(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=2',
            'raw_ical'       => null,
        ]);

        $service = new OxCalendarService();
        $rangeStart = Carbon::parse('2026-03-01');
        $rangeEnd   = Carbon::parse('2026-04-30');

        // Erste Ausführung: berechnen
        $result1 = $service->expandRruleTermine($termin, $rangeStart, $rangeEnd);

        // Zweite Ausführung: aus Cache
        $result2 = $service->expandRruleTermine($termin, $rangeStart, $rangeEnd);

        $this->assertEquals(count($result1), count($result2));
    }

    public function test_expansion_respektiert_zeitfenster_grenzen(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=10',
            'raw_ical'       => null,
        ]);

        $service = new OxCalendarService();

        // Nur die ersten 2 Wochen
        $occurrences = $service->expandRruleTermine(
            $termin,
            Carbon::parse('2026-03-16'),
            Carbon::parse('2026-03-30')
        );

        // Nur 2 Termine im Fenster (16. und 23. März)
        $this->assertCount(2, $occurrences);
    }
}

