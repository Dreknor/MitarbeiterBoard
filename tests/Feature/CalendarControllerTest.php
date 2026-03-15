<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\OxTerminTeilnehmer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_nicht_authentifizierter_user_wird_zu_login_umgeleitet(): void
    {
        $this->get('/calendar')->assertRedirect('/login');
    }

    public function test_user_ohne_permission_erhaelt_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/calendar')->assertForbidden();
    }

    public function test_index_seite_ist_fuer_user_mit_view_calendar_erreichbar(): void
    {
        $this->actingAsWithPermission('view calendar');
        $this->get('/calendar')->assertOk()->assertViewIs('calendar.index');
    }

    public function test_index_zeigt_nur_sichtbare_kalender(): void
    {
        $this->actingAsWithPermission('view calendar');
        OxCalendar::factory()->create(['name' => 'Sichtbar', 'sichtbar' => true]);
        OxCalendar::factory()->unsichtbar()->create(['name' => 'Unsichtbar']);

        $response = $this->get('/calendar')->assertOk();
        // Die Kalender werden als JSON in data-calendars übergeben
        $response->assertSee('Sichtbar');
        $response->assertDontSee('Unsichtbar');
    }

    public function test_gruppen_basierte_sichtbarkeit_user_sieht_nur_zugeordnete_kalender(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $userGroup = Group::factory()->create();
        $user->groups_rel()->attach($userGroup);

        $andereGruppe = Group::factory()->create();

        $meinKalender = OxCalendar::factory()->create(['name' => 'Mein Kalender']);
        $meinKalender->groups()->attach($userGroup->id, ['schreibbar' => false]);

        $andererKalender = OxCalendar::factory()->create(['name' => 'Anderer Kalender']);
        $andererKalender->groups()->attach($andereGruppe->id, ['schreibbar' => false]);

        $this->get('/calendar')
            ->assertSee('Mein Kalender')
            ->assertDontSee('Anderer Kalender');
    }

    public function test_admin_sieht_alle_kalender(): void
    {
        $this->actingAsWithPermission('view calendar', 'manage calendar');

        $andereGruppe = Group::factory()->create();
        $kalender = OxCalendar::factory()->create(['name' => 'Eingeschränkter Kalender']);
        $kalender->groups()->attach($andereGruppe->id, ['schreibbar' => false]);

        $this->get('/calendar')->assertSee('Eingeschränkter Kalender', false);
    }

    public function test_events_endpoint_liefert_json(): void
    {
        $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn' => '2026-03-15 14:00:00',
            'ende'   => '2026-03-15 16:00:00',
        ]);

        $this->getJson('/calendar/events?start=2026-03-01&end=2026-04-01')
            ->assertOk()
            ->assertJsonStructure([['id', 'title', 'start', 'end', 'allDay', 'color', 'extendedProps']]);
    }

    public function test_events_endpoint_filtert_nach_aktiven_kalendern(): void
    {
        $this->actingAsWithPermission('view calendar');
        $cal1 = OxCalendar::factory()->create();
        $cal2 = OxCalendar::factory()->create();
        OxTermin::factory()->create([
            'ox_calendar_id' => $cal1->id,
            'titel' => 'Termin 1',
            'beginn' => '2026-03-15 14:00:00',
            'ende'   => '2026-03-15 16:00:00',
        ]);
        OxTermin::factory()->create([
            'ox_calendar_id' => $cal2->id,
            'titel' => 'Termin 2',
            'beginn' => '2026-03-15 14:00:00',
            'ende'   => '2026-03-15 16:00:00',
        ]);

        $response = $this->getJson("/calendar/events?start=2026-03-01&end=2026-04-01&calendars={$cal1->id}");
        $response->assertOk();
        $data = $response->json();
        $calendarIds = collect($data)->pluck('extendedProps.calendarId')->unique()->values()->toArray();
        $this->assertEquals([$cal1->id], $calendarIds);
    }

    public function test_show_liefert_termin_details_als_json(): void
    {
        $this->actingAsWithPermission('view calendar');
        $termin = OxTermin::factory()->create();
        OxTerminTeilnehmer::create([
            'ox_termin_id' => $termin->id,
            'email'        => 'test@schule.de',
            'name'         => 'Test',
            'status'       => 'ACCEPTED',
        ]);

        $this->getJson("/calendar/termin/{$termin->id}")
            ->assertOk()
            ->assertJsonStructure(['id', 'titel', 'beginn', 'ende', 'kalender', 'teilnehmer', 'can_edit']);
    }

    public function test_show_verweigert_zugriff_auf_kalender_ohne_berechtigung(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $andereGruppe = Group::factory()->create();
        $kalender = OxCalendar::factory()->create();
        $kalender->groups()->attach($andereGruppe->id, ['schreibbar' => false]);
        $termin = OxTermin::factory()->create(['ox_calendar_id' => $kalender->id]);

        $this->getJson("/calendar/termin/{$termin->id}")->assertForbidden();
    }

    public function test_events_endpoint_gibt_leeres_array_ohne_start_end_zurueck(): void
    {
        $this->actingAsWithPermission('view calendar');
        $this->getJson('/calendar/events')->assertOk()->assertJson([]);
    }


    public function test_index_view_enthaelt_fullcalendar_container(): void
    {
        $this->actingAsWithPermission('view calendar');
        $this->get('/calendar')
            ->assertOk()
            ->assertSee('calendarEl', false)
            ->assertSee('calendarApp', false);
    }

    public function test_index_view_zeigt_sync_status(): void
    {
        $this->actingAsWithPermission('view calendar');
        OxCalendar::factory()->synchronisiert()->create();

        $this->get('/calendar')
            ->assertOk()
            ->assertSee('Zuletzt synchronisiert');
    }

    public function test_index_view_zeigt_warnung_bei_veralteter_synchronisation(): void
    {
        $this->actingAsWithPermission('view calendar');
        OxCalendar::factory()->create([
            'letzte_synchronisation' => now()->subHours(2),
        ]);

        $this->get('/calendar')
            ->assertOk()
            ->assertSee('möglicherweise nicht aktuell');
    }

    public function test_neuer_termin_button_nur_bei_schreibberechtigung_sichtbar(): void
    {
        $user = $this->actingAsWithPermission('view calendar', 'create calendar events');
        OxCalendar::factory()->schreibbar()->create();

        $this->get('/calendar')
            ->assertSee('Neuer Termin');
    }

    public function test_neuer_termin_button_versteckt_ohne_schreibberechtigung(): void
    {
        $this->actingAsWithPermission('view calendar');
        OxCalendar::factory()->create();

        $this->get('/calendar')
            ->assertDontSee('Neuer Termin');
    }
}


