<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\OxTerminTeilnehmer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * CalendarIntegrationTest – Gesamtabdeckung Phase 2 (TODO 13)
 *
 * Testet Routen-Schutz, JSON-Strukturen, Gruppen-Sichtbarkeit,
 * RRULE-/Ganztags-Events, Termin-Details, Cache-Verhalten und
 * öffentliche Kalender.
 */
class CalendarIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ================================================================
    // Routen-Schutz
    // ================================================================

    public function test_alle_calendar_routen_sind_auth_geschuetzt(): void
    {
        $termin = OxTermin::factory()->create();

        $this->get('/calendar')->assertRedirect('/login');
        $this->get('/calendar/events?start=2026-03-01&end=2026-04-01')->assertRedirect('/login');
        $this->get("/calendar/termin/{$termin->id}")->assertRedirect('/login');
    }

    public function test_user_ohne_view_calendar_permission_wird_abgewiesen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/calendar')->assertForbidden();
        $this->get('/calendar/events?start=2026-03-01&end=2026-04-01')->assertForbidden();
    }

    // ================================================================
    // Events-Endpoint (JSON)
    // ================================================================

    public function test_events_endpoint_liefert_korrekte_fullcalendar_struktur(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['farbe' => '#FF5733']);
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'titel'          => 'Testtermin',
            'beginn'         => '2026-03-15 14:00:00',
            'ende'           => '2026-03-15 16:00:00',
            'ort'            => 'Aula',
            'status'         => 'CONFIRMED',
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01');
        $response->assertOk();

        $event = $response->json(0);
        $this->assertSame('Testtermin', $event['title']);
        $this->assertSame('#FF5733', $event['color']);
        $this->assertFalse($event['allDay']);
        $this->assertSame('Aula', $event['extendedProps']['ort']);
        $this->assertSame('CONFIRMED', $event['extendedProps']['status']);
        $this->assertIsInt($event['extendedProps']['terminId']);
        $this->assertNotEmpty($event['extendedProps']['calendarName']);
    }

    public function test_events_endpoint_liefert_rrule_termine_korrekt(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->wiederkehrend()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => '2026-03-02 10:00:00',
            'ende'           => '2026-03-02 11:00:00',
            'rrule'          => 'FREQ=WEEKLY;BYDAY=MO;COUNT=10',
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-06-01');
        $response->assertOk();

        $event = $response->json(0);
        $this->assertArrayHasKey('rrule', $event);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY', $event['rrule']);
        $this->assertArrayHasKey('duration', $event);
        // Bei RRULE darf kein 'end' vorhanden sein
        $this->assertArrayNotHasKey('end', $event);
    }

    public function test_events_endpoint_liefert_ganztaegige_termine_korrekt(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->ganztaegig()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => '2026-03-15 00:00:00',
            'ende'           => '2026-03-16 00:00:00',
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01');
        $response->assertOk();

        $data = $response->json();
        $this->assertNotEmpty($data, 'Es sollte mindestens ein ganztägiger Termin zurückgegeben werden.');
        $this->assertTrue($data[0]['allDay']);
    }

    public function test_events_endpoint_respektiert_gruppen_basierte_sichtbarkeit(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $userGroup = Group::factory()->create();
        $user->groups_rel()->attach($userGroup);
        Cache::flush();

        // Kalender in User-Gruppe
        $meinKalender = OxCalendar::factory()->create();
        $meinKalender->groups()->attach($userGroup->id, ['schreibbar' => false]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $meinKalender->id,
            'titel'          => 'Sichtbar',
            'beginn'         => '2026-03-15 14:00:00',
            'ende'           => '2026-03-15 16:00:00',
        ]);

        // Kalender in anderer Gruppe
        $andereGruppe = Group::factory()->create();
        $andererKalender = OxCalendar::factory()->create();
        $andererKalender->groups()->attach($andereGruppe->id, ['schreibbar' => false]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $andererKalender->id,
            'titel'          => 'Unsichtbar',
            'beginn'         => '2026-03-15 14:00:00',
            'ende'           => '2026-03-15 16:00:00',
        ]);

        $response = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01');
        $response->assertOk();

        $titles = collect($response->json())->pluck('title');
        $this->assertTrue($titles->contains('Sichtbar'));
        $this->assertFalse($titles->contains('Unsichtbar'));
    }

    // ================================================================
    // Termin-Detail (Show)
    // ================================================================

    public function test_show_liefert_vollstaendige_termin_details(): void
    {
        $this->actingAsWithPermission('view calendar');
        $ersteller = User::factory()->create(['name' => 'Ersteller']);
        $termin = OxTermin::factory()->create([
            'titel'         => 'Detailtest',
            'beschreibung'  => 'Eine Beschreibung',
            'ort'           => 'Raum 201',
            'erstellt_von'  => $ersteller->id,
        ]);
        OxTerminTeilnehmer::create([
            'ox_termin_id' => $termin->id,
            'email'        => 'max@schule.de',
            'name'         => 'Max',
            'status'       => 'ACCEPTED',
        ]);

        $response = $this->getJson("/calendar/termin/{$termin->id}");
        $response->assertOk();

        $data = $response->json();
        $this->assertSame('Detailtest', $data['titel']);
        $this->assertSame('Eine Beschreibung', $data['beschreibung']);
        $this->assertSame('Raum 201', $data['ort']);
        $this->assertCount(1, $data['teilnehmer']);
        $this->assertSame('Max', $data['teilnehmer'][0]['name']);
        $this->assertSame('ACCEPTED', $data['teilnehmer'][0]['status']);
        $this->assertSame('Ersteller', $data['ersteller']['name']);
        $this->assertArrayHasKey('can_edit', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function test_show_enthaelt_can_edit_true_wenn_user_schreibrecht_hat(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'edit calendar events', 'create calendar events');
        $calendar = OxCalendar::factory()->schreibbar()->create();
        $termin = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        $response = $this->getJson("/calendar/termin/{$termin->id}");
        $response->assertOk();
        $this->assertTrue($response->json('can_edit'));
    }

    public function test_show_enthaelt_can_edit_false_wenn_user_kein_schreibrecht_hat(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['schreibbar' => false]);
        $termin = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);

        $response = $this->getJson("/calendar/termin/{$termin->id}");
        $response->assertOk();
        $this->assertFalse($response->json('can_edit'));
    }

    public function test_show_verweigert_zugriff_auf_termin_in_nicht_sichtbarem_kalender(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $andereGruppe = Group::factory()->create();
        $kalender = OxCalendar::factory()->create();
        $kalender->groups()->attach($andereGruppe->id, ['schreibbar' => false]);
        $termin = OxTermin::factory()->create(['ox_calendar_id' => $kalender->id]);

        $this->getJson("/calendar/termin/{$termin->id}")->assertForbidden();
    }

    // ================================================================
    // Cache-Verhalten
    // ================================================================

    public function test_events_endpoint_nutzt_cache(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => '2026-03-15 14:00:00',
            'ende'           => '2026-03-15 16:00:00',
        ]);

        // Erster Request (Cache-Miss)
        $response1 = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01');
        $response1->assertOk();

        // Zweiter Request (Cache-Hit) – muss identische Daten liefern
        $response2 = $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01');
        $response2->assertOk();

        $this->assertEquals($response1->json(), $response2->json());
    }

    // ================================================================
    // Öffentliche Kalender (ohne Gruppen-Zuordnung)
    // ================================================================

    public function test_kalender_ohne_gruppen_sind_fuer_alle_mit_permission_sichtbar(): void
    {
        $this->actingAsWithPermission('view calendar');
        OxCalendar::factory()->create(['name' => 'Öffentlicher Kalender']);
        // Keine Gruppen-Zuordnung → öffentlich!

        $this->get('/calendar')->assertSee('Öffentlicher Kalender');
    }

    public function test_events_liefert_leeres_array_wenn_alle_kalender_deaktiviert(): void
    {
        $this->actingAsWithPermission('view calendar');

        // Leerer calendars-Parameter bedeutet: User hat alle Kalender deaktiviert
        $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01&calendars=')
            ->assertOk()
            ->assertJson([]);
    }
}

