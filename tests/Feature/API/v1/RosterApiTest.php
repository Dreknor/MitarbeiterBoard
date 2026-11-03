<?php

namespace Tests\Feature\API\v1;

use App\Models\Absence;
use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\personal\Holiday;
use App\Models\personal\Roster;
use App\Models\personal\RosterChange;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\User;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;

/**
 * API v1 – Dienstplanung (/api/v1/roster/*).
 */
class RosterApiTest extends ApiTestCase
{
    use ZeitTestHelpers;

    private Group $abteilung;
    private User $planer;
    private User $ma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-09-23 10:00:00');
        Notification::fake();

        $this->abteilung = $this->abteilung();
        $this->abteilung->update(['needsRoster' => true]);
        $this->planer = $this->rechte(User::factory()->create(), 'create roster');
        $this->planer->groups_rel()->attach($this->abteilung->id);
        $this->ma = $this->mitarbeiter(vertrag: ['department_id' => $this->abteilung->id]);
        $this->ma->groups_rel()->attach($this->abteilung->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plan(array $attribute = []): Roster
    {
        return Roster::factory()->create(array_merge([
            'department_id' => $this->abteilung->id,
            'start_date' => '2026-09-28',
            'type' => 'normal',
        ], $attribute));
    }

    private function alsPlaner(): void
    {
        Sanctum::actingAs($this->planer->fresh(), ['paed-app']);
    }

    private function alsMitarbeiter(): void
    {
        Sanctum::actingAs($this->ma->fresh(), ['paed-app']);
    }

    // ── Zugriff ─────────────────────────────────────────────────────────

    public function test_ohne_token_401_und_ohne_planungsrecht_403(): void
    {
        $this->getJson(self::API.'/roster/mine')->assertUnauthorized();

        $this->alsMitarbeiter();
        $this->getJson(self::API.'/roster/departments')->assertForbidden();
        $this->postJson(self::API.'/roster/rosters', ['department_id' => $this->abteilung->id, 'start_date' => '2026-09-28', 'type' => 'normal'])
            ->assertForbidden();
    }

    public function test_planende_sehen_nur_ihre_abteilungen(): void
    {
        $fremd = Group::factory()->asDepartment()->create(['needsRoster' => true]);
        $fremderPlan = Roster::factory()->create(['department_id' => $fremd->id, 'start_date' => '2026-09-28']);
        $this->alsPlaner();

        $this->getJson(self::API.'/roster/departments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->abteilung->id);

        $this->getJson(self::API.'/roster/departments/'.$fremd->id.'/rosters')->assertForbidden();
        $this->getJson(self::API.'/roster/rosters/'.$fremderPlan->id)->assertForbidden();

        $this->rechte($this->planer, 'manage all rosters');
        $this->alsPlaner();
        $this->getJson(self::API.'/roster/rosters/'.$fremderPlan->id)->assertOk();
    }

    // ── Anlegen & Liste ─────────────────────────────────────────────────

    public function test_liste_liefert_nur_die_neuesten_vorlagen(): void
    {
        foreach (['2024-01-08', '2025-01-06', '2025-09-01', '2026-02-23', '2026-08-17'] as $start) {
            $this->plan(['type' => 'template', 'start_date' => $start]);
        }
        $woche = $this->plan();
        $this->alsPlaner();
        $url = self::API.'/roster/departments/'.$this->abteilung->id.'/rosters';

        $daten = $this->getJson($url)->assertOk()->json('data');
        $vorlagen = collect($daten)->where('type', 'template')->pluck('start_date')->sort()->values()->all();
        $this->assertSame(['2025-09-01', '2026-02-23', '2026-08-17'], $vorlagen);
        $this->assertContains($woche->id, collect($daten)->pluck('id'));

        $this->getJson($url.'?templates=5')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson($url.'?type=template&templates=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.start_date', '2026-08-17');
        $this->getJson($url.'?templates=99')->assertUnprocessable();
    }


    public function test_plan_anlegen_uebernimmt_urlaub_und_meldet_belegte_woche(): void
    {
        Holiday::factory()->for($this->ma, 'employe')->approved()->create(['start_date' => '2026-09-29', 'end_date' => '2026-09-29', 'days' => 1]);
        $this->alsPlaner();

        $antwort = $this->postJson(self::API.'/roster/rosters', [
            'department_id' => $this->abteilung->id,
            'start_date' => '2026-09-30',
            'type' => 'normal',
            'additional_weeks' => ['2026-10-05'],
        ])->assertCreated()
            ->assertJsonPath('data.start_date', '2026-09-28')
            ->assertJsonPath('data.published', false)
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonCount(1, 'copies')
            ->assertJsonPath('copies.0.start_date', '2026-10-05');

        $urlaub = collect($antwort->json('data.events'))->firstWhere('date', '2026-09-29');
        $this->assertSame('Urlaub', $urlaub['event']);
        $this->assertTrue($urlaub['is_absence']);

        $this->postJson(self::API.'/roster/rosters', [
            'department_id' => $this->abteilung->id,
            'start_date' => '2026-09-28',
            'type' => 'normal',
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->getJson(self::API.'/roster/departments/'.$this->abteilung->id.'/rosters?from=2026-09-21&to=2026-10-11')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.open_changes', 0);
    }

    public function test_vorlage_einer_fremden_abteilung_wird_abgelehnt(): void
    {
        $fremdeVorlage = Roster::factory()->create(['type' => 'template', 'start_date' => '2026-01-05']);
        $this->alsPlaner();

        $this->postJson(self::API.'/roster/rosters', [
            'department_id' => $this->abteilung->id,
            'start_date' => '2026-09-28',
            'type' => 'normal',
            'template_id' => $fremdeVorlage->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('template_id');
    }

    // ── Arbeitszeiten & Termine ─────────────────────────────────────────

    public function test_arbeitszeit_setzen_ist_idempotenter_zielzustand(): void
    {
        $roster = $this->plan();
        $this->alsPlaner();
        $url = self::API.'/roster/rosters/'.$roster->id.'/working-times';
        $daten = ['employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '07:30', 'end' => '15:00', 'function' => 'Frühdienst'];

        $this->putJson($url, $daten)->assertOk()
            ->assertJsonPath('data.start', '07:30')
            ->assertJsonPath('data.function', 'Frühdienst')
            ->assertJsonPath('warning', null);
        $this->putJson($url, $daten)->assertOk();
        $this->assertSame(1, WorkingTime::where('roster_id', $roster->id)->count());

        // frei
        $this->putJson($url, ['employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => null, 'end' => null])
            ->assertOk()->assertJsonPath('data.start', null);

        // außerhalb der Woche, fremde Person, Ende vor Start
        $this->putJson($url, ['employe_id' => $this->ma->id, 'date' => '2026-10-05', 'start' => '08:00', 'end' => '12:00'])->assertUnprocessable();
        $this->putJson($url, ['employe_id' => User::factory()->create()->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '12:00'])->assertUnprocessable();
        $this->putJson($url, ['employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '12:00', 'end' => '08:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('end');
    }

    public function test_termine_anlegen_aendern_und_loeschen(): void
    {
        $roster = $this->plan();
        $kollege = $this->mitarbeiter(vertrag: ['department_id' => $this->abteilung->id]);
        $this->alsPlaner();
        $url = self::API.'/roster/rosters/'.$roster->id.'/events';

        // Merkliste
        $this->postJson($url, ['event' => 'Elternabend', 'date' => '2026-09-30', 'start' => '17:00', 'end' => '18:00'])
            ->assertCreated()->assertJsonPath('data.0.employe_id', null);

        $erster = $this->postJson($url, ['event' => 'Hofaufsicht', 'date' => '2026-09-29', 'start' => '09:00', 'end' => '10:00', 'employe_ids' => [$this->ma->id]])
            ->assertCreated()->json('data.0.id');

        // Überschneidung beim Anlegen: übersprungen und gemeldet
        $this->postJson($url, ['event' => 'Mittag', 'date' => '2026-09-29', 'start' => '09:30', 'end' => '10:30', 'employe_ids' => [$this->ma->id, $kollege->id]])
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employe_id', $kollege->id)
            ->assertJsonPath('skipped_employe_ids', [$this->ma->id]);

        // fremde Person
        $this->postJson($url, ['event' => 'X', 'date' => '2026-09-29', 'start' => '11:00', 'end' => '12:00', 'employe_ids' => [User::factory()->create()->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('employe_ids');

        // Ändern: Überschneidung mit dem Termin des Kollegen → 409
        $this->putJson(self::API.'/roster/events/'.$erster, ['event' => 'Hofaufsicht', 'date' => '2026-09-29', 'start' => '09:00', 'end' => '10:00', 'employe_id' => $kollege->id])
            ->assertStatus(409);

        $this->putJson(self::API.'/roster/events/'.$erster, ['event' => 'Hofaufsicht', 'date' => '2026-10-01', 'start' => '09:00', 'end' => '10:00', 'employe_id' => null])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-01')
            ->assertJsonPath('data.employe_id', null);

        $this->deleteJson(self::API.'/roster/events/'.$erster)->assertNoContent();
        $this->assertSoftDeleted('roster_events', ['id' => $erster]);
    }

    public function test_abwesenheits_markierungen_sind_schreibgeschuetzt(): void
    {
        $roster = $this->plan();
        $markierung = RosterEvents::create([
            'roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29',
            'start' => '08:00', 'end' => '14:30', 'event' => 'Urlaub', 'source' => RosterEvents::SOURCE_ABWESENHEIT,
        ]);
        $this->alsPlaner();

        $this->deleteJson(self::API.'/roster/events/'.$markierung->id)->assertUnprocessable();
        $this->putJson(self::API.'/roster/events/'.$markierung->id, ['event' => 'Dienst', 'date' => '2026-09-29', 'start' => '08:00', 'end' => '09:00', 'employe_id' => null])
            ->assertUnprocessable();
    }

    // ── Veröffentlichung ────────────────────────────────────────────────

    public function test_veroeffentlichen_aenderungen_mitteilen_und_zurueckziehen(): void
    {
        $roster = $this->plan();
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '14:00']);
        $this->alsPlaner();

        $this->postJson(self::API.'/roster/rosters/'.$roster->id.'/publish')
            ->assertOk()->assertJsonPath('data.published', true);
        Notification::assertSentTo($this->ma, ZeitwirtschaftNotification::class);

        $this->putJson(self::API.'/roster/rosters/'.$roster->id.'/working-times', ['employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '09:00', 'end' => '14:00'])
            ->assertOk();
        $this->assertSame(1, RosterChange::where('roster_id', $roster->id)->whereNull('notified_at')->count());

        $this->getJson(self::API.'/roster/rosters/'.$roster->id)->assertJsonPath('data.open_changes', 1);
        $this->postJson(self::API.'/roster/rosters/'.$roster->id.'/notify-changes')->assertOk()->assertJsonPath('data.notified', 1);

        $this->postJson(self::API.'/roster/rosters/'.$roster->id.'/unpublish')->assertOk()->assertJsonPath('data.published', false);

        $vorlage = $this->plan(['type' => 'template', 'start_date' => '2026-01-05']);
        $this->postJson(self::API.'/roster/rosters/'.$vorlage->id.'/publish')->assertUnprocessable();
    }

    public function test_planansicht_liefert_nachbarn_vorschlaege_hinweise_und_aenderungen(): void
    {
        $vorher = $this->plan(['start_date' => '2026-09-21']);
        $roster = $this->plan();
        $nachher = $this->plan(['start_date' => '2026-10-05']);
        RosterEvents::create(['roster_id' => $vorher->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-22', 'start' => '09:00', 'end' => '10:00', 'event' => 'Hausaufgaben']);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '14:00']);
        $this->alsPlaner();

        $this->postJson(self::API.'/roster/rosters/'.$roster->id.'/publish', ['notify' => false])->assertOk();
        $this->putJson(self::API.'/roster/rosters/'.$roster->id.'/working-times', ['employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '09:00', 'end' => '14:00'])->assertOk();

        $note = $this->postJson(self::API.'/roster/rosters/'.$roster->id.'/notes', ['text' => 'Ausflug am Mittwoch'])
            ->assertCreated()->assertJsonPath('data.text', 'Ausflug am Mittwoch')->json('data.id');

        $this->getJson(self::API.'/roster/rosters/'.$roster->id)
            ->assertOk()
            ->assertJsonPath('data.previous_id', $vorher->id)
            ->assertJsonPath('data.next_id', $nachher->id)
            ->assertJsonPath('data.event_names.0', 'Hausaufgaben')
            ->assertJsonPath('data.notes.0.text', 'Ausflug am Mittwoch')
            ->assertJsonPath('data.changes.0.employe_id', $this->ma->id)
            ->assertJsonPath('data.changes.0.notified', false);

        $this->deleteJson(self::API.'/roster/notes/'.$note)->assertNoContent();
        $this->getJson(self::API.'/roster/rosters/'.$roster->id)->assertJsonCount(0, 'data.notes');

        // Mitarbeitende sehen die Planungsdaten nicht
        $this->alsMitarbeiter();
        $this->getJson(self::API.'/roster/rosters/'.$roster->id)->assertOk()->assertJsonMissingPath('data.notes');
        $this->postJson(self::API.'/roster/rosters/'.$roster->id.'/notes', ['text' => 'x'])->assertForbidden();
    }

    public function test_tag_leeren_behaelt_abwesenheiten(): void
    {
        $roster = $this->plan();
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '14:00']);
        RosterEvents::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '09:00', 'end' => '10:00', 'event' => 'Hausaufgaben']);
        RosterEvents::create(['roster_id' => $roster->id, 'employe_id' => $this->planer->id, 'date' => '2026-09-29', 'start' => '00:00', 'end' => '23:59', 'event' => 'Urlaub', 'source' => RosterEvents::SOURCE_ABWESENHEIT]);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-30', 'start' => '08:00', 'end' => '14:00']);
        $this->alsPlaner();

        $this->deleteJson(self::API.'/roster/rosters/'.$roster->id.'/days/2026-09-29')->assertNoContent();

        $this->assertSame(0, WorkingTime::where('roster_id', $roster->id)->whereDate('date', '2026-09-29')->count());
        $this->assertSame(1, WorkingTime::where('roster_id', $roster->id)->whereDate('date', '2026-09-30')->count());
        $this->assertSame(['Urlaub'], RosterEvents::where('roster_id', $roster->id)->whereDate('date', '2026-09-29')->pluck('event')->all());

        $this->deleteJson(self::API.'/roster/rosters/'.$roster->id.'/days/2026-11-02')->assertStatus(422);
    }

    public function test_vergangene_veroeffentlichte_plaene_sind_nicht_loeschbar(): void
    {
        $alt = $this->plan(['start_date' => '2026-09-07', 'published' => true]);
        $neu = $this->plan();
        $this->alsPlaner();

        $this->deleteJson(self::API.'/roster/rosters/'.$alt->id)->assertUnprocessable();
        $this->deleteJson(self::API.'/roster/rosters/'.$neu->id)->assertNoContent();
        $this->assertSoftDeleted('rosters', ['id' => $neu->id]);
    }

    // ── Sicht der Mitarbeitenden ────────────────────────────────────────

    public function test_mitarbeitende_sehen_nur_veroeffentlichte_plaene_ohne_fremde_abwesenheitsgruende(): void
    {
        $roster = $this->plan();
        $kollege = $this->mitarbeiter(vertrag: ['department_id' => $this->abteilung->id]);
        RosterEvents::create([
            'roster_id' => $roster->id, 'employe_id' => $kollege->id, 'date' => '2026-09-29',
            'start' => '08:00', 'end' => '14:30', 'event' => 'krank', 'source' => RosterEvents::SOURCE_ABWESENHEIT,
        ]);
        $this->alsMitarbeiter();

        $this->getJson(self::API.'/roster/rosters/'.$roster->id)->assertForbidden();

        $roster->update(['published' => true]);
        $antwort = $this->getJson(self::API.'/roster/rosters/'.$roster->id)
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonMissingPath('data.conflicts')
            ->assertJsonMissingPath('data.hours');

        $this->assertSame('abwesend', collect($antwort->json('data.events'))->firstWhere('employe_id', $kollege->id)['event']);
    }

    public function test_mein_dienstplan_liefert_nur_veroeffentlichte_dienste(): void
    {
        $roster = $this->plan();
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '07:00', 'end' => '13:00', 'function' => 'Früh']);
        RosterEvents::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '09:00', 'end' => '09:30', 'event' => 'Pause']);
        Absence::factory()->create(['users_id' => $this->ma->id, 'start' => '2026-10-01', 'end' => '2026-10-01', 'reason' => 'Fortbildung']);
        $this->alsMitarbeiter();

        $url = self::API.'/roster/mine?from=2026-09-28&to=2026-10-04';
        $leer = $this->getJson($url)->assertOk()->assertJsonCount(7, 'data.days')->json('data.days.1');
        $this->assertSame([], $leer['shifts']);

        $roster->update(['published' => true]);
        $antwort = $this->getJson($url)->assertOk()
            ->assertJsonPath('data.days.1.shifts.0.start', '07:00')
            ->assertJsonPath('data.days.1.shifts.0.department.id', $this->abteilung->id)
            ->assertJsonPath('data.days.1.events.0.event', 'Pause')
            ->assertJsonPath('data.days.3.absence', 'Fortbildung')
            ->assertJsonPath('data.rosters.0.id', $roster->id);

        $this->assertSame('2026-10-04', $antwort->json('data.to'));

        $this->getJson(self::API.'/roster/mine?from=2026-01-01&to=2026-12-31')->assertUnprocessable();
    }

    // ── Kalender-Import ─────────────────────────────────────────────────

    public function test_kalender_import_vorschau_und_uebernahme_in_die_merkliste(): void
    {
        $roster = $this->plan();
        $kalender = OxCalendar::factory()->create(['sichtbar' => true, 'name' => 'Schulkalender']);
        $serie = OxTermin::factory()->create([
            'ox_calendar_id' => $kalender->id, 'titel' => 'Teamsitzung',
            'beginn' => '2026-09-21 13:00:00', 'ende' => '2026-09-21 16:00:00',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE;COUNT=6',
        ]);
        $ganztag = OxTermin::factory()->create([
            'ox_calendar_id' => $kalender->id, 'titel' => 'Projekttag', 'ganztaegig' => true,
            'beginn' => '2026-10-02 00:00:00', 'ende' => '2026-10-03 00:00:00',
        ]);
        OxTermin::factory()->create(['ox_calendar_id' => $kalender->id, 'beginn' => '2026-10-07 09:00:00', 'ende' => '2026-10-07 10:00:00']);
        $this->rechte($this->planer, 'view calendar');
        $this->alsPlaner();
        $url = self::API.'/roster/rosters/'.$roster->id.'/calendar-import';

        $vorschau = $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.calendar_id', $kalender->id)
            ->assertJsonPath('data.calendars.0.name', 'Schulkalender')
            ->assertJsonPath('data.from', '2026-09-28')
            ->assertJsonPath('data.to', '2026-10-04')
            ->assertJsonCount(3, 'data.occurrences') // Mo + Mi der Serie, Projekttag
            ->json('data.occurrences');

        $schluessel = collect($vorschau)->pluck('key')->all();
        $this->assertContains($serie->id.'|2026-09-28|13:00:00|16:00:00', $schluessel);

        $this->postJson($url, ['keys' => [...$schluessel, '999999|2026-09-29|09:00:00|10:00:00']])
            ->assertCreated()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.employe_id', null)
            ->assertJsonPath('invalid_keys', ['999999|2026-09-29|09:00:00|10:00:00']);

        // Zeiten auf das Tagesfenster (08:00–14:30) begrenzt, ganztägig füllt es aus
        $this->assertDatabaseHas('roster_events', ['roster_id' => $roster->id, 'ox_termin_id' => $serie->id, 'date' => '2026-09-30', 'start' => '13:00:00', 'end' => '14:30:00']);
        $this->assertDatabaseHas('roster_events', ['roster_id' => $roster->id, 'ox_termin_id' => $ganztag->id, 'date' => '2026-10-02', 'start' => '08:00:00', 'end' => '14:30:00']);

        // Wiederholung: nichts doppelt
        $this->postJson($url, ['keys' => $schluessel])
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonCount(3, 'skipped_keys');
        $this->assertSame(3, RosterEvents::where('roster_id', $roster->id)->whereNotNull('ox_termin_id')->count());

        $this->getJson($url.'?calendar_id='.$kalender->id)->assertOk()->assertJsonPath('data.occurrences.0.imported', true);
    }

    public function test_kalender_import_nur_aus_sichtbaren_kalendern(): void
    {
        $roster = $this->plan();
        $gruppenKalender = OxCalendar::factory()->create(['sichtbar' => true]);
        $gruppenKalender->groups()->attach(Group::factory()->create()->id);
        $termin = OxTermin::factory()->create(['ox_calendar_id' => $gruppenKalender->id, 'beginn' => '2026-09-29 09:00:00', 'ende' => '2026-09-29 10:00:00']);
        $this->rechte($this->planer, 'view calendar');
        $url = self::API.'/roster/rosters/'.$roster->id.'/calendar-import';

        $this->alsMitarbeiter();
        $this->getJson($url)->assertForbidden();

        $this->alsPlaner();
        $this->getJson($url.'?calendar_id='.$gruppenKalender->id)->assertUnprocessable()->assertJsonValidationErrors('calendar_id');
        $this->postJson($url, ['keys' => [$termin->id.'|2026-09-29|09:00:00|10:00:00']])
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonCount(1, 'invalid_keys');
    }

    public function test_token_payload_meldet_planungsrecht(): void
    {
        $this->rechte($this->planer, 'view paed diary');
        $this->alsPlaner();

        $this->getJson(self::API.'/auth/me')->assertOk()->assertJsonPath('data.permissions.plan_rosters', true);
    }

    // ── Dienstplan-App: Login ohne Pädagogen-App-Recht ───────────────────

    public function test_dienstplan_app_token_fuer_abteilungsmitglieder_ohne_tagebuchrecht(): void
    {
        $this->ma->update(['password' => \Illuminate\Support\Facades\Hash::make('geheim123')]);
        $login = fn (array $extra = []) => $this->postJson(self::API.'/auth/token', [
            'email' => $this->ma->email, 'password' => 'geheim123', 'device_name' => 'Handy',
        ] + $extra);

        $login()->assertForbidden();
        $login(['app' => 'unbekannt'])->assertUnprocessable()->assertJsonValidationErrors('app');

        $token = $login(['app' => 'dienstplan'])->assertCreated()
            ->assertJsonPath('user.apps.dienstplan', true)
            ->assertJsonPath('user.apps.paed', false)
            ->json('access_token');

        $this->withToken($token)->getJson(self::API.'/auth/me')->assertOk()->assertJsonPath('data.id', $this->ma->id);
        $this->withToken($token)->getJson(self::API.'/roster/mine')->assertOk();
        $this->withToken($token)->getJson(self::API.'/classes')->assertForbidden();
    }

    public function test_dienstplan_app_ohne_abteilung_und_planungsrecht_403(): void
    {
        $fremd = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('geheim123')]);

        $this->postJson(self::API.'/auth/token', [
            'email' => $fremd->email, 'password' => 'geheim123', 'device_name' => 'Handy', 'app' => 'dienstplan',
        ])->assertForbidden()->assertJsonPath('message', 'Keine Berechtigung für die Dienstplan-App.');
    }

    public function test_idempotenz_auch_fuer_dienstplan_tokens(): void
    {
        $roster = $this->plan();
        $this->alsPlaner();
        $key = (string) \Illuminate\Support\Str::uuid();
        $daten = ['event' => 'Teamsitzung', 'date' => '2026-09-29', 'start' => '14:00', 'end' => '15:00'];

        $this->postJson(self::API."/roster/rosters/{$roster->id}/events", $daten, ['Idempotency-Key' => $key])->assertCreated();
        $this->postJson(self::API."/roster/rosters/{$roster->id}/events", $daten, ['Idempotency-Key' => $key])->assertCreated();

        $this->assertSame(1, RosterEvents::where('roster_id', $roster->id)->where('event', 'Teamsitzung')->count());
    }
}
