<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\Wochenplan\WpAufgabeStatus;
use App\Models\Wochenplan\WpPlan;
use App\Services\PaedDiaryTaskService;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpStatusService;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/** Status je Kind (offen/teilweise/erledigt), Matrix und Tagebuch-Anbindung. */
class StatusJeKindTest extends TestCase
{
    use WochenplanTestHilfen;

    private Klasse $klasse;

    private WpPlan $plan;

    private Schueler $mia;

    private Schueler $tom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextcloudFake(false);
        $this->klasse = Klasse::factory()->create();
        $this->plan = $this->klassenplan($this->klasse);
        $this->mia = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Mia', 'nachname' => 'A']);
        $this->tom = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Tom', 'nachname' => 'B']);
        $this->actingAs($this->paedagoge([$this->klasse]));
    }

    public function test_status_setzen_mit_drei_stufen_und_notiz(): void
    {
        $aufgabe = $this->fachMitAufgaben($this->plan, ['Lesen'])->aufgaben->first();

        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'teilweise', 'notiz' => 'S. 12 fehlt'])
            ->assertOk()->assertJsonPath('eintrag.status', 'teilweise')->assertJsonPath('eintrag.notiz', 'S. 12 fehlt');
        $this->assertDatabaseHas('wp_aufgabe_status', ['schueler_id' => $this->mia->id, 'status' => 'teilweise', 'user_id' => auth()->id()]);

        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'erledigt'])->assertOk();
        $this->assertSame(1, WpAufgabeStatus::count(), 'kein Verlauf – nur der aktuelle Stand');

        // offen ohne Notiz → Eintrag entfällt
        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'offen'])->assertOk();
        $this->assertSame(0, WpAufgabeStatus::count());

        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'fertig'])->assertStatus(422);
    }

    public function test_kinderplan_aufgaben_gelten_statt_klassenplan(): void
    {
        $klassenAufgabe = $this->fachMitAufgaben($this->plan, ['Lesen'])->aufgaben->first();
        $kp = app(WochenplanService::class)->kinderplanErstellen($this->plan, $this->mia);

        // Mia hat einen Kinderplan → Klassenplan-Aufgabe gilt für sie nicht
        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $klassenAufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'erledigt'])
            ->assertStatus(422);
        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $kp->aufgaben()->first()->id, 'schueler_id' => $this->mia->id, 'status' => 'erledigt'])
            ->assertOk();
        // Tom ohne Kinderplan → Klassenplan
        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $klassenAufgabe->id, 'schueler_id' => $this->tom->id, 'status' => 'erledigt'])
            ->assertOk();
    }

    public function test_fremde_klasse_darf_nicht_abhaken(): void
    {
        $aufgabe = $this->fachMitAufgaben($this->plan, ['Lesen'])->aufgaben->first();
        $this->actingAs($this->paedagoge([Klasse::factory()->create()]));

        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'erledigt'])
            ->assertForbidden();
        $this->get(route('wp.fortschritt', $this->plan))->assertForbidden();
    }

    public function test_matrix_und_mehrfachauswahl(): void
    {
        $fach = $this->fachMitAufgaben($this->plan, ['Lesen', 'Rechnen']);
        [$lesen, $rechnen] = $fach->aufgaben->all();

        $this->get(route('wp.fortschritt', $this->plan))->assertOk()->assertSee('wpMatrix', false);

        $antwort = $this->postJson(route('wp.fortschritt.mehrere', $this->plan), [
            'status' => 'erledigt',
            'zellen' => [
                ['wp_aufgabe_id' => $lesen->id, 'schueler_id' => $this->mia->id],
                ['wp_aufgabe_id' => $lesen->id, 'schueler_id' => $this->tom->id],
                ['wp_aufgabe_id' => $rechnen->id, 'schueler_id' => $this->mia->id],
            ],
        ])->assertOk()->assertJsonPath('anzahl', 3);

        $zeilen = collect($antwort->json('matrix.zeilen'))->keyBy('schueler.id');
        $this->assertSame(['erledigt' => 2, 'teilweise' => 0, 'gesamt' => 2], $zeilen[$this->mia->id]['zusammenfassung']);
        $this->assertSame(['erledigt' => 1, 'teilweise' => 0, 'gesamt' => 2], $zeilen[$this->tom->id]['zusammenfassung']);
    }

    public function test_tagebuch_abgleich_in_beide_richtungen(): void
    {
        $this->tagebuchAnbindung(true);
        $kp = app(WochenplanService::class)->kinderplanErstellen($this->plan, $this->mia);
        $this->fachMitAufgaben($kp, []);
        $task = PaedDiaryTask::factory()->create(['klasse_id' => $this->klasse->id, 'schueler_id' => $this->mia->id, 'status' => 'open']);
        $planFach = $kp->planFaecher()->firstOrFail();

        $this->postJson(route('wp.aufgabe.from-diary', [$kp, $planFach]), ['diary_task_id' => $task->id, 'aufgabe' => $task->title])
            ->assertOk()->assertJsonPath('plan.faecher.0.aufgaben.0.tagebuch.id', $task->id);
        $aufgabe = $kp->aufgaben()->where('paed_diary_task_id', $task->id)->firstOrFail();

        // Wochenplan → Tagebuch
        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'teilweise'])->assertOk();
        $this->assertSame('open', $task->fresh()->status, '„teilweise“ schließt nicht');
        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'erledigt'])->assertOk();
        $this->assertSame('closed', $task->fresh()->status);
        $this->postJson(route('wp.status.setzen'), ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'offen'])->assertOk();
        $this->assertSame('open', $task->fresh()->status, 'Zurücknehmen öffnet wieder');

        // Tagebuch → Wochenplan
        app(PaedDiaryTaskService::class)->close($task->fresh());
        $this->assertDatabaseHas('wp_aufgabe_status', ['wp_aufgabe_id' => $aufgabe->id, 'schueler_id' => $this->mia->id, 'status' => 'erledigt']);
        $task->fresh()->update(['status' => 'open', 'closed_at' => null]);
        $this->assertDatabaseMissing('wp_aufgabe_status', ['wp_aufgabe_id' => $aufgabe->id, 'status' => 'erledigt']);
    }

    public function test_ausgeschaltete_anbindung(): void
    {
        $this->tagebuchAnbindung(false);
        $kp = app(WochenplanService::class)->kinderplanErstellen($this->plan, $this->mia);
        $this->fachMitAufgaben($kp, []);
        $task = PaedDiaryTask::factory()->create(['klasse_id' => $this->klasse->id, 'schueler_id' => $this->mia->id]);

        $this->postJson(route('wp.aufgabe.from-diary', [$kp, $kp->planFaecher()->first()]), ['diary_task_id' => $task->id, 'aufgabe' => 'X'])
            ->assertForbidden();
        $this->getJson(route('wp.schueler.woche', $this->mia))->assertNotFound();
        $this->getJson(route('wp.klasse.woche', $this->klasse))->assertNotFound();

        // Verknüpfung bleibt erhalten, wirkt aber nicht
        $aufgabe = app(WochenplanService::class)->aufgabeAnlegen($kp->planFaecher()->first(), ['aufgabe' => 'X', 'paed_diary_task_id' => $task->id]);
        app(WpStatusService::class)->setzen($aufgabe, $this->mia, 'erledigt', null, null);
        $this->assertSame('open', $task->fresh()->status);

        // Matrix funktioniert immer
        $this->get(route('wp.fortschritt', $kp))->assertOk();
    }

    public function test_tagebuch_wochenansicht_und_seitenleiste(): void
    {
        $this->tagebuchAnbindung(true);
        $aufgaben = $this->fachMitAufgaben($this->plan, ['Lesen', 'Rechnen', 'Schreiben'])->aufgaben;
        $status = app(WpStatusService::class);
        $status->setzen($aufgaben[0], $this->mia, 'erledigt', null, null);
        $status->setzen($aufgaben[1], $this->mia, 'teilweise', null, null);

        $this->getJson(route('wp.klasse.woche', $this->klasse))->assertOk()
            ->assertJsonPath('schueler.'.$this->mia->id, ['erledigt' => 1, 'teilweise' => 1, 'gesamt' => 3])
            ->assertJsonPath('schueler.'.$this->tom->id, ['erledigt' => 0, 'teilweise' => 0, 'gesamt' => 3]);

        $this->getJson(route('wp.schueler.woche', $this->mia))->assertOk()
            ->assertJsonPath('plaene.0.faecher.0.aufgaben.0.status', 'erledigt')
            ->assertJsonPath('zusammenfassung.gesamt', 3);

        // andere Woche → kein Plan
        $this->getJson(route('wp.schueler.woche', [$this->mia, 'datum' => now()->addWeeks(3)->toDateString()]))->assertOk()
            ->assertJsonCount(0, 'plaene');
    }

    public function test_tagebuch_seiten_enthalten_wochenplan_nur_mit_anbindung(): void
    {
        $user = $this->paedagoge([$this->klasse], ['create wochenplan', 'view paed diary']);
        $this->actingAs($user);

        $this->tagebuchAnbindung(false);
        $this->assertStringContainsString('"aktiv":false', view('paedDiary.partials.wochenplan')->render());

        $this->tagebuchAnbindung(true);
        $html = view('paedDiary.partials.wochenplan')->render();
        $this->assertStringContainsString('"aktiv":true', $html);
        $this->assertStringContainsString('wpt-panel', $html);
    }

    public function test_schuelerakte_listet_wochenplaene(): void
    {
        $this->tagebuchAnbindung(true);
        $this->fachMitAufgaben($this->plan, ['Lesen']);

        $liste = app(WpStatusService::class)->planeFuerSchuelerakte($this->mia);
        $this->assertCount(1, $liste);
        $this->assertSame($this->plan->id, $liste[0]['id']);

        $html = view('paedDiary.partials.wochenplaene-schueler', ['wochenplaene' => $liste])->render();
        $this->assertStringContainsString($this->plan->name, $html);
    }

    public function test_benutzerloeschung_kennt_die_neuen_tabellen(): void
    {
        $tabellen = collect(app(\App\Services\UserDeletion\RuleRegistry::class)->all())->map(fn ($r) => $r->table)->all();
        $this->assertContains('wp_aufgabe_status', $tabellen);
        $this->assertContains('wp_dateien', $tabellen);
        $this->assertContains('wp_bausteine', $tabellen);
    }
}
