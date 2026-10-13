<?php

namespace Tests\Feature\API\v1;

use App\Models\Absence;
use App\Models\Group;
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

    public function test_token_payload_meldet_planungsrecht(): void
    {
        $this->rechte($this->planer, 'view paed diary');
        $this->alsPlaner();

        $this->getJson(self::API.'/auth/me')->assertOk()->assertJsonPath('data.permissions.plan_rosters', true);
    }
}
