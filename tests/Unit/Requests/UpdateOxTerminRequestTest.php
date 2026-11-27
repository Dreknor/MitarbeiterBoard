<?php

namespace Tests\Unit\Requests;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Validierung und optimistische Sperre beim Bearbeiten eines Termins (PUT calendar.update).
 * Entspricht TODO 15 der calendar-ox-Reihe. Die Regeln stehen in CalendarController::update()
 * (terminRegeln()); `expected_updated_at` ist optional – wird es mitgeschickt (das Formular tut
 * das immer), weist ein veralteter Stand die Änderung ab.
 */
class UpdateOxTerminRequestTest extends TestCase
{
    private function terminInSchreibbaremKalender(): OxTermin
    {
        $this->actingAsWithPermission('edit calendar events');
        $kalender = OxCalendar::factory()->schreibbar()->create();

        return OxTermin::factory()->create(['ox_calendar_id' => $kalender->id]);
    }

    public function test_UpdateOxTerminRequest_veralteter_Stand_wird_abgewiesen(): void
    {
        Http::fake();
        $termin = $this->terminInSchreibbaremKalender();
        $titelVorher = $termin->titel;

        $this->from('/calendar')->put(route('calendar.update', $termin), [
            'kalender_ids'        => [$termin->ox_calendar_id],
            'titel'               => 'Aktualisiert',
            'beginn'              => '2026-03-20 14:00:00',
            'ende'                => '2026-03-20 16:00:00',
            'expected_updated_at' => $termin->updated_at->copy()->subHour()->toIso8601String(),
        ])->assertRedirect('/calendar')->assertSessionHas('type', 'warning');

        $this->assertSame($titelVorher, $termin->fresh()->titel);
        Http::assertNothingSent();
    }

    public function test_UpdateOxTerminRequest_Validierung_analog_zu_Store(): void
    {
        Http::fake();
        $termin = $this->terminInSchreibbaremKalender();

        $this->put(route('calendar.update', $termin), [
            'kalender_ids'        => [$termin->ox_calendar_id],
            'beginn'              => '2026-03-20 16:00:00',
            'ende'                => '2026-03-20 14:00:00',
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertSessionHasErrors(['titel', 'ende']);

        Http::assertNothingSent();
    }
}
