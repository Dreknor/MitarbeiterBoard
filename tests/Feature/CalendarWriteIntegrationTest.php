<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\OxTermin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-Integrations-Tests für Schreiboperationen (Gesamtabdeckung Phase 3).
 * Entspricht TODO 18 der calendar-ox-Reihe.
 */
class CalendarWriteIntegrationTest extends TestCase
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
    // Wiederkehrende Termine
    // ================================================================

    public function test_Wiederkehrender_Termin_Erstellen_mit_RRULE(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Wöchentliche Besprechung',
            'beginn'         => '2026-03-23 10:00:00',
            'ende'           => '2026-03-23 11:00:00',
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=10',
        ])->assertRedirect();

        $termin = OxTermin::where('titel', 'Wöchentliche Besprechung')->first();
        $this->assertNotNull($termin);
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO;COUNT=10', $termin->rrule);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY', $termin->raw_ical);
    }

    public function test_Wiederkehrender_Termin_iCal_enthaelt_korrekte_RRULE(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Monatlich',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
            'rrule'          => 'FREQ=MONTHLY;COUNT=6',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'RRULE:FREQ=MONTHLY;COUNT=6');
        });
    }

    // ================================================================
    // Ganztägige Termine
    // ================================================================

    public function test_Ganztaegiger_Termin_Erstellen_korrekt(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Fortbildungstag',
            'beginn'         => '2026-04-01',
            'ende'           => '2026-04-02',
            'ganztaegig'     => true,
        ])->assertRedirect();

        $termin = OxTermin::where('titel', 'Fortbildungstag')->first();
        $this->assertTrue($termin->ganztaegig);
    }

    // ================================================================
    // Gruppen-basierte Schreibberechtigung (detailliert)
    // ================================================================

    public function test_User_in_Gruppe_mit_schreibbar_true_darf_Termin_erstellen(): void
    {
        $user   = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $gruppe = Group::factory()->create();
        $user->groups_rel()->attach($gruppe);

        $calendar = OxCalendar::factory()->schreibbar()->create();
        $calendar->groups()->attach($gruppe->id, ['schreibbar' => true]);

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Erlaubt',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertRedirect()->assertSessionHas('type', 'success');
    }

    public function test_User_in_Gruppe_mit_schreibbar_false_darf_NICHT_erstellen(): void
    {
        $user   = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $gruppe = Group::factory()->create();
        $user->groups_rel()->attach($gruppe);

        $calendar = OxCalendar::factory()->schreibbar()->create();
        $calendar->groups()->attach($gruppe->id, ['schreibbar' => false]); // Nur Leserecht!

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Verboten',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertForbidden();
    }

    public function test_Admin_darf_in_alle_schreibbaren_Kalender_schreiben(): void
    {
        $this->actingAsWithPermission('view calendar', 'create calendar events', 'manage calendar');

        $andereGruppe = Group::factory()->create();
        $calendar     = OxCalendar::factory()->schreibbar()->create();
        $calendar->groups()->attach($andereGruppe->id, ['schreibbar' => true]);

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Admin-Termin',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertRedirect()->assertSessionHas('type', 'success');
    }

    public function test_Nicht_schreibbarer_Kalender_blockiert_alle(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->create(['schreibbar' => false]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Test',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertForbidden();
    }

    // ================================================================
    // Audit-Logging
    // ================================================================

    public function test_Terminerstellung_wird_in_ox_sync_log_protokolliert(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Geloggt',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ]);

        $log = OxSyncLog::where('aktion', 'create')->first();
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Geloggt', $log->details['titel']);
    }

    public function test_Terminloeschung_wird_in_ox_sync_log_protokolliert(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'edit calendar events', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        Http::fake(['*' => Http::response('', 204)]);

        $this->delete(route('calendar.destroy', $termin));

        $this->assertTrue(OxSyncLog::where('aktion', 'delete')->exists());
    }

    // ================================================================
    // Edge Cases
    // ================================================================

    public function test_Termin_mit_maximal_langem_Titel_255_Zeichen(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => str_repeat('A', 255),
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertRedirect();

        $this->assertSame(255, strlen(OxTermin::first()->titel));
    }

    public function test_Termin_mit_leerer_Beschreibung_und_Ort(): void
    {
        $user     = $this->actingAsWithPermission('view calendar', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();

        Http::fake(['*' => Http::response('', 201, ['ETag' => '"e1"'])]);

        $this->post(route('calendar.store'), [
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Minimal',
            'beginn'         => '2026-03-20 14:00:00',
            'ende'           => '2026-03-20 16:00:00',
        ])->assertRedirect();

        $termin = OxTermin::first();
        $this->assertNull($termin->beschreibung);
        $this->assertNull($termin->ort);
    }
}

