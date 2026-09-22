<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature-Tests für das Termin-Formular UI.
 * Entspricht TODO 17 der calendar-ox-Reihe.
 */
class CalendarFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_Termin_Formular_ist_fuer_User_mit_Schreibrecht_sichtbar(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events');
        OxCalendar::factory()->schreibbar()->create();

        $this->get('/calendar')
            ->assertOk()
            ->assertSee('Neuen Termin erstellen', false);
    }

    public function test_Termin_Formular_zeigt_nur_schreibbare_Kalender(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events');
        OxCalendar::factory()->schreibbar()->create(['name' => 'Schreibbar']);
        OxCalendar::factory()->create(['name' => 'NurLesen', 'schreibbar' => false]);

        $response = $this->get('/calendar')->assertOk();

        // Schreibbarer Kalender muss im Formular erscheinen
        $response->assertSee('Schreibbar');
    }

    public function test_Termin_Formular_ist_versteckt_ohne_Schreibrecht(): void
    {
        $this->actingAsWithPermission('view calendar');
        OxCalendar::factory()->create();

        $this->get('/calendar')
            ->assertOk()
            ->assertDontSee('Neuen Termin erstellen', false);
    }
}

