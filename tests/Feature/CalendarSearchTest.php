<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature-Tests für CalendarController::search() (TODO 27).
 */
class CalendarSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_suche_findet_termin_nach_titel(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['sichtbar' => true]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Lehrerkonferenz März',
        ]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Elternabend',
            'beschreibung'   => null,
            'ort'            => null,
        ]);

        $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'Lehrer']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['titel' => 'Lehrerkonferenz März']);
    }

    public function test_suche_findet_termin_nach_ort(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['sichtbar' => true]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Besprechung',
            'ort'            => 'Raum 204',
        ]);

        $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'Raum 204']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_suche_findet_termin_nach_beschreibung(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['sichtbar' => true]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Meeting',
            'beschreibung'   => 'Jahresplanung besprechen',
        ]);

        $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'Jahresplanung']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_suche_ignoriert_unsichtbare_kalender(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $unsichtbar = OxCalendar::factory()->unsichtbar()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $unsichtbar->id,
            'titel'          => 'Geheimer Termin',
        ]);

        $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'Geheim']))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_suche_erfordert_mindestens_2_zeichen(): void
    {
        $user = $this->actingAsWithPermission('view calendar');

        $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'L']))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_suche_erfordert_authentifizierung(): void
    {
        $this->getJson(route('calendar.search', ['q' => 'Test']))
            ->assertUnauthorized();
    }

    public function test_suche_erfordert_view_calendar_permission(): void
    {
        $this->actingAsWithPermission('edit calendar events');

        $this->getJson(route('calendar.search', ['q' => 'Test']))
            ->assertForbidden();
    }

    public function test_suche_begrenzt_ergebnisse(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['sichtbar' => true]);
        OxTermin::factory()->count(30)->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Testtermin',
        ]);

        $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'Testtermin', 'limit' => 5]))
            ->assertOk()
            ->assertJsonCount(5);
    }

    public function test_suche_gibt_korrekte_json_struktur_zurueck(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['sichtbar' => true]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Strukturtest',
            'ort'            => 'Raum 1',
        ]);

        $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'Struktur']))
            ->assertOk()
            ->assertJsonStructure([[
                'id',
                'titel',
                'ort',
                'beginn',
                'ende',
                'beginn_iso',
                'ganztaegig',
                'kalender' => ['name', 'farbe'],
            ]]);
    }

    public function test_suche_begrenzt_limit_auf_maximal_50(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['sichtbar' => true]);
        OxTermin::factory()->count(60)->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Massentest',
        ]);

        // limit=100 wird auf 50 begrenzt
        $response = $this->actingAs($user)
            ->getJson(route('calendar.search', ['q' => 'Massen', 'limit' => 100]))
            ->assertOk();

        $this->assertLessThanOrEqual(50, count($response->json()));
    }
}


