<?php

namespace Tests\Unit\Requests;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use Tests\TestCase;

/**
 * Tests für UpdateOxTerminRequest – Validierungsregeln.
 * Entspricht TODO 15 der calendar-ox-Reihe.
 */
class UpdateOxTerminRequestTest extends TestCase
{
    public function test_UpdateOxTerminRequest_expected_updated_at_ist_Pflichtfeld(): void
    {
        $this->actingAsWithPermission('edit calendar events');
        $termin = OxTermin::factory()->create();

        $this->put(route('calendar.update', $termin), [
            'ox_calendar_id' => $termin->ox_calendar_id,
            'titel'          => 'Aktualisiert',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertSessionHasErrors('expected_updated_at');
    }

    public function test_UpdateOxTerminRequest_Validierung_analog_zu_Store(): void
    {
        $this->actingAsWithPermission('edit calendar events');
        $termin = OxTermin::factory()->create();

        $this->put(route('calendar.update', $termin), [
            'ox_calendar_id'      => $termin->ox_calendar_id,
            'beginn'              => '2026-03-20 16:00:00',
            'ende'                => '2026-03-20 14:00:00',
            'expected_updated_at' => now()->toIso8601String(),
        ])->assertSessionHasErrors(['titel', 'ende']);
    }
}

