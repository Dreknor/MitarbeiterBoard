<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-Tests für CalendarController::move() – Drag-and-Drop-Terminverschiebung.
 */
class CalendarMoveTest extends TestCase
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

    // =========================================================================
    // Erfolgsfall
    // =========================================================================

    public function test_termin_per_drag_and_drop_verschieben(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => now()->addDay(),
            'ende'           => now()->addDay()->addHours(2),
        ]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'ganztaegig'          => false,
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'updated_at'])
        ->assertJson(['success' => true]);
    }

    public function test_termin_verschieben_aktualisiert_beginn_und_ende_in_db(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => '2026-03-15 10:00:00',
            'ende'           => '2026-03-15 12:00:00',
        ]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => '2026-03-18 14:00:00',
            'ende'                => '2026-03-18 16:00:00',
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertOk();

        $termin->refresh();
        $this->assertTrue($termin->beginn->format('Y-m-d H:i') === '2026-03-18 14:00');
        $this->assertTrue($termin->ende->format('Y-m-d H:i') === '2026-03-18 16:00');
    }

    public function test_updated_at_wird_in_response_zurueckgegeben(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        Http::fake(['*' => Http::response('', 204)]);

        $response = $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(1)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('updated_at'));
    }

    // =========================================================================
    // RRULE – Wiederkehrende Termine
    // =========================================================================

    public function test_rrule_termin_kann_nicht_verschoben_werden(): void
    {
        $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO',
        ]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])
        ->assertUnprocessable() // 422
        ->assertJsonFragment(['error' => 'Wiederkehrende Termine können nicht per Drag-and-Drop verschoben werden.']);
    }

    // =========================================================================
    // Berechtigungen
    // =========================================================================

    public function test_user_ohne_edit_permission_wird_abgewiesen(): void
    {
        $this->actingAsWithPermission('view calendar'); // Kein edit calendar events

        $termin = OxTermin::factory()->create();

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_nicht_authentifizierter_user_wird_zu_login_umgeleitet(): void
    {
        $termin = OxTermin::factory()->create();

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertUnauthorized();
    }

    public function test_kein_schreibrecht_wegen_nicht_schreibbarem_kalender(): void
    {
        $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->create(['schreibbar' => false]); // nicht schreibbar!
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_kein_schreibrecht_wegen_falscher_gruppe(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'edit calendar events');
        // User ist nicht in der Gruppe des Kalenders
        $andereGruppe = Group::factory()->create();

        $calendar = OxCalendar::factory()->schreibbar()->create();
        $calendar->groups()->attach($andereGruppe->id, ['schreibbar' => true]);

        $termin = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_user_in_schreibbarer_gruppe_darf_verschieben(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'edit calendar events');

        $gruppe = Group::factory()->create();
        $user->groups_rel()->attach($gruppe);

        $calendar = OxCalendar::factory()->schreibbar()->create();
        $calendar->groups()->attach($gruppe->id, ['schreibbar' => true]);

        $termin = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertOk();
    }

    public function test_admin_darf_immer_verschieben(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'edit calendar events', 'manage calendar');

        $andereGruppe = Group::factory()->create();
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $calendar->groups()->attach($andereGruppe->id, ['schreibbar' => true]);
        // Admin ist nicht in andereGruppe → trotzdem erlaubt

        $termin = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertOk();
    }

    // =========================================================================
    // Optimistic Locking
    // =========================================================================

    public function test_optimistic_locking_bei_veralteter_updated_at(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(3)->toIso8601String(),
            'ende'                => now()->addDays(3)->addHours(2)->toIso8601String(),
            'expected_updated_at' => now()->subHour()->toIso8601String(), // veraltet!
        ])
        ->assertStatus(409)
        ->assertJson(['reload' => true])
        ->assertJsonFragment(['error' => 'Der Termin wurde zwischenzeitlich geändert. Bitte Seite neu laden.']);
    }

    // =========================================================================
    // Validierung
    // =========================================================================

    public function test_fehlende_pflichtfelder_werden_zurueckgewiesen(): void
    {
        $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        $this->patchJson(route('calendar.move', $termin), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['beginn', 'ende', 'expected_updated_at']);
    }

    public function test_ende_vor_beginn_wird_abgelehnt(): void
    {
        $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => '2026-03-20 16:00:00',
            'ende'                => '2026-03-20 14:00:00', // Ende vor Beginn!
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])->assertUnprocessable();
    }

    // =========================================================================
    // Rate-Limiting
    // =========================================================================

    public function test_rate_limiting_gilt_auch_fuer_patch(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        Http::fake(['*' => Http::response('', 204)]);

        // Rate-Limiter zurücksetzen
        app(\Illuminate\Cache\RateLimiter::class)->clear('calendar-write,' . $user->id);

        // Bis zum Limit senden (30 Requests/Minute)
        for ($i = 0; $i < 30; $i++) {
            $this->patchJson(route('calendar.move', $termin), [
                'beginn'              => now()->addDays($i + 1)->toIso8601String(),
                'ende'                => now()->addDays($i + 1)->addHours(1)->toIso8601String(),
                'expected_updated_at' => $termin->fresh()->updated_at->toIso8601String(),
            ]);
        }

        // 31. Request → Rate-Limit überschritten
        $this->patchJson(route('calendar.move', $termin), [
            'beginn'              => now()->addDays(32)->toIso8601String(),
            'ende'                => now()->addDays(32)->addHours(1)->toIso8601String(),
            'expected_updated_at' => $termin->fresh()->updated_at->toIso8601String(),
        ])->assertStatus(429);
    }

    // =========================================================================
    // Events-Endpoint liefert updatedAt
    // =========================================================================

    public function test_events_endpoint_liefert_updated_at_im_extended_props(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => '2026-03-15 10:00:00',
            'ende'           => '2026-03-15 11:00:00',
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01');
        $response->assertOk();

        $event = $response->json(0);
        $this->assertArrayHasKey('updatedAt', $event['extendedProps']);
        $this->assertNotEmpty($event['extendedProps']['updatedAt']);
    }

    public function test_rrule_events_haben_editable_false_im_json(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->wiederkehrend()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => '2026-03-03 10:00:00',
            'ende'           => '2026-03-03 11:00:00',
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=4',
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01');
        $response->assertOk();

        $event = $response->json(0);
        $this->assertFalse($event['editable']);
    }
}

