<?php

namespace Tests\Unit\Requests;

use App\Models\OxCalendar;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests für StoreOxTerminRequest – Validierungsregeln.
 * Entspricht TODO 15 der calendar-ox-Reihe.
 */
class StoreOxTerminRequestTest extends TestCase
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

    public function test_StoreOxTerminRequest_Titel_ist_Pflichtfeld(): void
    {
        $this->actingAsWithPermission('create calendar events');
        $calendar = OxCalendar::factory()->create();

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertSessionHasErrors('titel');
    }

    public function test_StoreOxTerminRequest_Ende_muss_nach_Beginn_liegen(): void
    {
        $this->actingAsWithPermission('create calendar events');
        $calendar = OxCalendar::factory()->create();

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Test',
            'beginn'         => '2026-03-20 16:00:00',
            'ende'           => '2026-03-20 14:00:00',
        ])->assertSessionHasErrors('ende');
    }

    public function test_StoreOxTerminRequest_Beginn_in_Vergangenheit_ist_erlaubt(): void
    {
        $this->actingAsWithPermission('create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        // HTTP-Fake damit der CalDAV-Aufruf im Controller nicht fehlschlägt
        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Rückwirkend',
            'beginn'         => '2025-01-01 14:00:00',
            'ende'           => '2025-01-01 16:00:00',
        ])->assertSessionDoesntHaveErrors('beginn');
    }

    public function test_StoreOxTerminRequest_Beschreibung_max_5000_Zeichen(): void
    {
        $this->actingAsWithPermission('create calendar events');
        $calendar = OxCalendar::factory()->create();

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Test',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
            'beschreibung'   => str_repeat('x', 5001),
        ])->assertSessionHasErrors('beschreibung');
    }

    public function test_StoreOxTerminRequest_Kalender_muss_existieren(): void
    {
        $this->actingAsWithPermission('create calendar events');

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => 99999,
            'titel'          => 'Test',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertSessionHasErrors('ox_calendar_id');
    }
}

