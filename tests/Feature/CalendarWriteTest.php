<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-Tests für CalendarController – Schreiboperationen.
 * Entspricht TODO 16 der calendar-ox-Reihe.
 */
class CalendarWriteTest extends TestCase
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

    // ================================================================
    // Termin erstellen
    // ================================================================

    public function test_Termin_erstellen_Erfolg(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Neue Konferenz',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('type', 'success');

        $this->assertTrue(OxTermin::where('titel', 'Neue Konferenz')->exists());
    }

    public function test_Termin_erstellen_User_ohne_Permission_wird_abgewiesen(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Test',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertForbidden();
    }

    public function test_Termin_erstellen_Gruppen_Schreibschutz_wird_geprueft(): void
    {
        $user        = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $andereGruppe = Group::factory()->create();

        $calendar = OxCalendar::factory()->schreibbar()->create();
        $calendar->groups()->attach($andereGruppe->id, ['schreibbar' => true]);

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Test',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertForbidden();
    }

    public function test_Termin_erstellen_CalDAV_Fehler_zeigt_Fehlermeldung(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 500)]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Test',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('type', 'danger');

        $this->assertSame(0, OxTermin::count());
    }

    // ================================================================
    // Termin bearbeiten
    // ================================================================

    public function test_Termin_bearbeiten_Erfolg(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Alt',
        ]);

        Http::fake(['*' => Http::response('', 200, ['ETag' => '"new-etag"'])]);

        $this->put(route('calendar.update', $termin), [
            'ox_calendar_id'      => $calendar->id,
            'titel'               => 'Neu',
            'beginn'              => '2026-03-20 14:00:00',
            'ende'                => '2026-03-20 16:00:00',
            'expected_updated_at' => $termin->updated_at->toIso8601String(),
        ])
        ->assertRedirect()
        ->assertSessionHas('type', 'success');

        $termin->refresh();
        $this->assertSame('Neu', $termin->titel);
    }

    public function test_Termin_bearbeiten_Optimistic_Locking_verhindert_Ueberschreiben(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Original',
        ]);

        // Simuliere: Termin wurde zwischenzeitlich geändert
        $alteUpdatedAt = $termin->updated_at->subMinutes(5)->toIso8601String();

        $this->put(route('calendar.update', $termin), [
            'ox_calendar_id'      => $calendar->id,
            'titel'               => 'Überschrieben',
            'beginn'              => '2026-03-20 14:00:00',
            'ende'                => '2026-03-20 16:00:00',
            'expected_updated_at' => $alteUpdatedAt,
        ])
        ->assertRedirect()
        ->assertSessionHas('type', 'warning');

        $termin->refresh();
        $this->assertSame('Original', $termin->titel); // Nicht geändert!
    }

    // ================================================================
    // Termin löschen
    // ================================================================

    public function test_Termin_loeschen_Erfolg(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->delete(route('calendar.destroy', $termin))
            ->assertRedirect()
            ->assertSessionHas('type', 'success');

        $this->assertNull(OxTermin::find($termin->id));
    }

    public function test_Termin_loeschen_Ohne_Berechtigung_wird_abgewiesen(): void
    {
        $this->actingAsWithPermission('view calendar');
        $termin = OxTermin::factory()->create();

        $this->delete(route('calendar.destroy', $termin))->assertForbidden();
    }

    // ================================================================
    // Rate-Limiting
    // ================================================================

    public function test_Rate_Limiting_auf_Schreibendpunkten_aktiv(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e"'])]);

        // Middleware-Stack der Route enthält throttle:calendar-write
        $rateLimiter = app(RateLimiter::class);

        // Den Limiter auf 30 Requests (Limit erreicht) für diesen User vorladen
        // Key: md5('calendar-write' . $user->id) – entspricht ThrottleRequests-Logik
        $key = md5('calendar-write' . $user->id);
        for ($i = 0; $i < 30; $i++) {
            $rateLimiter->hit($key, 60);
        }

        // Nächster Request (31.) muss 429 zurückgeben
        $response = $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Zu viele Requests',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ]);

        $response->assertStatus(429);
    }
}



