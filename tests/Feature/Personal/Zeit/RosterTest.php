<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\personal\Employment;
use App\Models\personal\Holiday;
use App\Models\personal\Roster;
use App\Models\personal\RosterChange;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\User;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use ZeitTestHelpers;

    private \App\Models\Group $abteilung;
    private User $planer;
    private User $ma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-09-23 10:00:00');
        Notification::fake();

        $this->abteilung = $this->abteilung();
        $this->planer = $this->rechte(User::factory()->create(), 'create roster');
        $this->planer->groups_rel()->attach($this->abteilung->id);
        $this->ma = $this->mitarbeiter(vertrag: ['department_id' => $this->abteilung->id]);
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

    public function test_planende_sehen_nur_ihre_abteilungen(): void
    {
        $fremd = Roster::factory()->create(['start_date' => '2026-09-28']);

        $this->actingAs($this->planer)->get(route('roster.show', $this->plan()->id))->assertOk();
        $this->actingAs($this->planer)->get(route('roster.show', $fremd->id))->assertForbidden();

        $this->rechte($this->planer, 'manage all rosters');
        $this->actingAs($this->planer->fresh())->get(route('roster.show', $fremd->id))->assertOk();
    }

    public function test_neuer_plan_beruecksichtigt_genehmigten_urlaub_und_feiertage(): void
    {
        Holiday::factory()->for($this->ma, 'employe')->approved()->create(['start_date' => '2026-09-29', 'end_date' => '2026-09-29', 'days' => 1]);
        Holiday::factory()->for($this->ma, 'employe')->rejected()->create(['start_date' => '2026-09-30', 'end_date' => '2026-09-30']);

        $this->actingAs($this->planer)->post(route('roster.store'), [
            'department_id' => $this->abteilung->id,
            'start_date' => '2026-09-30', // wird auf Montag 28.09. gesetzt
            'type' => 'normal',
        ])->assertRedirect();

        $roster = Roster::where('department_id', $this->abteilung->id)->firstOrFail();
        $this->assertSame('2026-09-28', $roster->start_date->toDateString());

        $markierungen = $roster->events()->where('employe_id', $this->ma->id)->where('source', RosterEvents::SOURCE_ABWESENHEIT)->get()
            ->mapWithKeys(fn ($e) => [$e->date->toDateString() => $e->event]);

        $this->assertSame('Urlaub', $markierungen['2026-09-29']);
        $this->assertArrayNotHasKey('2026-09-30', $markierungen->all(), 'Abgelehnter Urlaub darf nicht eingetragen werden');
        $this->assertSame('Tag der Deutschen Einheit', $markierungen['2026-10-03']);
    }

    public function test_spaeter_genehmigter_urlaub_erscheint_als_konflikt(): void
    {
        $roster = $this->plan();
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-10-01', 'start' => '08:00', 'end' => '14:00']);

        Holiday::factory()->for($this->ma, 'employe')->approved()->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'days' => 1]);

        $daten = $this->actingAs($this->planer)->getJson(route('roster.data', $roster->id))->assertOk()->json();

        $this->assertContains('Urlaub – trotzdem eingeplant', $daten['konflikte'][$this->ma->id]['2026-10-01']);
    }

    public function test_arbeitszeit_nur_fuer_personen_der_abteilung_und_innerhalb_der_woche(): void
    {
        $roster = $this->plan();
        $fremd = $this->mitarbeiter();

        $this->actingAs($this->planer)->postJson(route('roster.working-time.store'), [
            'roster_id' => $roster->id, 'employe_id' => $fremd->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '12:00',
        ])->assertStatus(422);

        $this->actingAs($this->planer)->postJson(route('roster.working-time.store'), [
            'roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-10-10', 'start' => '08:00', 'end' => '12:00',
        ])->assertStatus(422);

        $this->actingAs($this->planer)->postJson(route('roster.working-time.store'), [
            'roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '12:00',
        ])->assertOk();

        $this->assertSame('08:00', WorkingTime::where('employe_id', $this->ma->id)->firstOrFail()->start->format('H:i'));
    }

    public function test_termine_anlegen_ohne_person_landen_in_der_merkliste_und_kollisionen_werden_gemeldet(): void
    {
        $roster = $this->plan();

        $this->actingAs($this->planer)->postJson(route('roster.events.store', $roster->id), [
            'event' => 'Elterngespräch', 'date' => '2026-09-29', 'start' => '09:00', 'end' => '10:00', 'employes' => [],
        ])->assertOk();
        $this->assertNull(RosterEvents::where('event', 'Elterngespräch')->value('employe_id'));

        $this->actingAs($this->planer)->postJson(route('roster.events.store', $roster->id), [
            'event' => 'Frühdienst', 'date' => '2026-09-29', 'start' => '08:00', 'end' => '10:00', 'employes' => [$this->ma->id],
        ])->assertOk()->assertJsonPath('type', 'success');

        $this->actingAs($this->planer)->postJson(route('roster.events.store', $roster->id), [
            'event' => 'Konferenz', 'date' => '2026-09-29', 'start' => '09:30', 'end' => '11:00', 'employes' => [$this->ma->id],
        ])->assertOk()->assertJsonPath('type', 'warning');

        $this->assertSame(0, RosterEvents::where('event', 'Konferenz')->count());
    }

    public function test_veroeffentlichen_benachrichtigt_und_aenderungen_werden_mitgeteilt(): void
    {
        $roster = $this->plan();
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '14:00']);

        $this->actingAs($this->planer)->post(route('roster.publish', $roster->id))->assertRedirect();
        $this->assertTrue($roster->fresh()->published);
        Notification::assertSentTo($this->ma, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'roster_published');

        // Änderung nach Veröffentlichung wird protokolliert …
        $this->actingAs($this->planer)->postJson(route('roster.working-time.store'), [
            'roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '07:00', 'end' => '14:00',
        ])->assertOk();
        $this->assertSame(1, RosterChange::where('roster_id', $roster->id)->whereNull('notified_at')->count());

        // … und gezielt mitgeteilt
        $this->actingAs($this->planer)->post(route('roster.notify-changes', $roster->id))->assertRedirect();
        Notification::assertSentTo($this->ma, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'roster_changed');
        $this->assertSame(0, RosterChange::where('roster_id', $roster->id)->whereNull('notified_at')->count());
    }

    public function test_mitarbeitende_sehen_nur_veroeffentlichte_plaene(): void
    {
        $this->ma->groups_rel()->attach($this->abteilung->id);
        $entwurf = $this->plan();
        WorkingTime::create(['roster_id' => $entwurf->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '14:00']);

        $this->actingAs($this->ma)->get(route('roster.export.pdf', $entwurf->id))->assertForbidden();
        $this->actingAs($this->ma)->get(route('roster.mine', ['woche' => '2026-09-28']))->assertOk()->assertDontSee('08:00–14:00 Uhr');

        $entwurf->update(['published' => true]);
        $this->actingAs($this->ma)->get(route('roster.mine', ['woche' => '2026-09-28']))->assertOk()->assertSee('08:00–14:00 Uhr');
    }

    public function test_persoenlicher_kalender_feed(): void
    {
        $roster = $this->plan(['published' => true]);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '14:00', 'function' => 'Frühdienst']);

        $this->actingAs($this->ma)->post(route('roster.feed-token'))->assertRedirect();
        $token = $this->ma->fresh()->roster_feed_token;
        $this->assertNotEmpty($token);

        $antwort = $this->get(route('roster.feed', $token))->assertOk();
        $this->assertStringContainsString('SUMMARY:Dienst: Frühdienst', $antwort->getContent());
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20260929T080000', $antwort->getContent());

        $this->get(route('roster.feed', str_repeat('x', 48)))->assertNotFound();
    }

    public function test_in_weitere_wochen_kopieren(): void
    {
        $roster = $this->plan();
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-29', 'start' => '08:00', 'end' => '14:00']);

        $this->actingAs($this->planer)->post(route('roster.copy', $roster->id), ['wochen' => ['2026-10-05', '2026-10-12']])->assertRedirect();

        $kopie = Roster::where('department_id', $this->abteilung->id)->whereDate('start_date', '2026-10-05')->firstOrFail();
        $this->assertFalse((bool) $kopie->published);
        $this->assertSame(1, $kopie->working_times()->whereDate('date', '2026-10-06')->count());
    }

    public function test_editor_und_uebersicht_sind_erreichbar(): void
    {
        $roster = $this->plan();

        $this->actingAs($this->planer)->get(route('roster.index'))->assertOk()->assertSee($this->abteilung->name);
        $this->actingAs($this->planer)->get(route('roster.create', $this->abteilung->id))->assertOk();
        $this->actingAs($this->planer)->get(route('roster.show', $roster->id))->assertOk()->assertSee('dienstplan-daten', false);
        $this->actingAs($this->planer)->get(route('roster.autoPlan', $roster->id))->assertOk();
    }
}
