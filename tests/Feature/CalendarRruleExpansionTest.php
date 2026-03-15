<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature-Tests für serverseitige RRULE-Expansion im events-Endpoint (TODO 25).
 */
class CalendarRruleExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_events_endpoint_ohne_expand_rrule_liefert_rrule_string(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=4',
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-30')
            ->assertOk();

        $events = $response->json();
        $rruleEvent = collect($events)->first(fn ($e) => isset($e['rrule']));

        $this->assertNotNull($rruleEvent, 'RRULE-Termin sollte als einzelnes Event mit rrule-String zurückkommen');
        $this->assertStringContainsString('RRULE:', $rruleEvent['rrule']);
    }

    public function test_events_endpoint_mit_expand_rrule_liefert_einzeltermine(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=4',
            'raw_ical'       => null,
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-30&expand_rrule=1')
            ->assertOk();

        $events = $response->json();

        // Alle Events sollten start+end haben, kein rrule-String
        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertArrayNotHasKey('rrule', $event, 'Bei expand_rrule darf kein rrule-String im Event sein');
            $this->assertArrayHasKey('start', $event);
            $this->assertArrayHasKey('end', $event);
        }
    }

    public function test_expand_rrule_liefert_korrekte_anzahl_einzeltermine(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=4',
            'raw_ical'       => null,
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-30&expand_rrule=1')
            ->assertOk();

        // COUNT=4 → 4 Einzeltermine
        $this->assertCount(4, $response->json());
    }

    public function test_expandierte_events_haben_isoccurrence_flag(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=2',
            'raw_ical'       => null,
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-30&expand_rrule=1')
            ->assertOk();

        $events = $response->json();
        foreach ($events as $event) {
            $this->assertTrue(
                $event['extendedProps']['isOccurrence'] ?? false,
                'Expandierte Events müssen isOccurrence=true haben'
            );
        }
    }

    public function test_events_endpoint_ohne_rrule_termine_unveraendert_bei_expand(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Einmaltermin',
            'beginn'         => Carbon::parse('2026-03-20 14:00:00'),
            'ende'           => Carbon::parse('2026-03-20 15:00:00'),
            'rrule'          => null,
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-30&expand_rrule=1')
            ->assertOk();

        $events = $response->json();
        $this->assertCount(1, $events);
        $this->assertEquals('Einmaltermin', $events[0]['title']);
    }

    public function test_unterschiedliche_cache_keys_fuer_expand_und_normal(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => Carbon::parse('2026-03-16 10:00:00'),
            'ende'           => Carbon::parse('2026-03-16 11:00:00'),
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=4',
            'raw_ical'       => null,
        ]);

        // Normale Anfrage
        $normalResponse = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-30')
            ->assertOk();

        // Expand-Anfrage
        $expandResponse = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-30&expand_rrule=1')
            ->assertOk();

        $normalEvents  = $normalResponse->json();
        $expandEvents  = $expandResponse->json();

        // Normal: 1 Event mit rrule-String; Expand: 4 Einzeltermine
        $this->assertCount(1, $normalEvents);
        $this->assertCount(4, $expandEvents);
    }
}

