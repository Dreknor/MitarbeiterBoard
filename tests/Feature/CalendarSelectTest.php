<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature-Tests für Terminauswahl direkt im FullCalendar (TODO 31).
 *
 * Da die Logik rein client-seitig ist, prüfen diese Tests:
 * - Die korrekten CSS-Klassen in der Kalender-View (calendar-no-create)
 * - Das data-can-edit Attribut
 * - Das data-can-create Attribut
 */
class CalendarSelectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // =========================================================================
    // calendar-no-create CSS-Klasse
    // =========================================================================

    public function test_kalender_ansicht_hat_no_create_klasse_ohne_create_berechtigung(): void
    {
        $this->actingAsWithPermission('view calendar');
        // Kein 'create calendar events' → calendar-no-create

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('calendar-no-create');
    }

    public function test_kalender_ansicht_hat_kein_no_create_wenn_user_erstellen_darf(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events');
        OxCalendar::factory()->schreibbar()->create();
        // schreibbar, keine Gruppen → canCreate = true

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertDontSee('calendar-no-create');
    }

    public function test_kalender_ansicht_hat_no_create_wenn_kein_schreibbarer_kalender(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events');
        OxCalendar::factory()->create(['schreibbar' => false]);
        // Nur nicht-schreibbarer Kalender → canCreate = false

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('calendar-no-create');
    }

    // =========================================================================
    // data-can-create Attribut
    // =========================================================================

    public function test_data_can_create_ist_false_ohne_berechtigung(): void
    {
        $this->actingAsWithPermission('view calendar');

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('data-can-create="false"', false);
    }

    public function test_data_can_create_ist_true_mit_berechtigung_und_schreibbarem_kalender(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events');
        OxCalendar::factory()->schreibbar()->create();

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('data-can-create="true"', false);
    }

    // =========================================================================
    // data-can-edit Attribut
    // =========================================================================

    public function test_data_can_edit_ist_false_ohne_edit_berechtigung(): void
    {
        $this->actingAsWithPermission('view calendar');

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('data-can-edit="false"', false);
    }

    public function test_data_can_edit_ist_true_mit_edit_berechtigung_und_schreibbarem_kalender(): void
    {
        $this->actingAsWithPermission('view calendar', 'edit calendar events');
        OxCalendar::factory()->schreibbar()->create();

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('data-can-edit="true"', false);
    }

    public function test_data_can_edit_ist_false_wenn_kein_schreibbarer_kalender(): void
    {
        $this->actingAsWithPermission('view calendar', 'edit calendar events');
        OxCalendar::factory()->create(['schreibbar' => false]);

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('data-can-edit="false"', false);
    }

    // =========================================================================
    // Kombination beider Berechtigungen
    // =========================================================================

    public function test_user_mit_allen_rechten_sieht_weder_no_create_noch_false_attribute(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events', 'edit calendar events');
        OxCalendar::factory()->schreibbar()->create();

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertDontSee('calendar-no-create')
            ->assertSee('data-can-create="true"', false)
            ->assertSee('data-can-edit="true"', false);
    }
}

