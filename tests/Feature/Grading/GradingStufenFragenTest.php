<?php

namespace Tests\Feature\Grading;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingSessionStage;
use App\Models\GradingStage;
use App\Models\GradingStudentAnswer;
use App\Models\GradingSystem;
use App\Models\GradingTeacherAssessment;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Services\Api\StudentDataService;
use App\Services\GradingSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Graduierungsfragen je Stufe: Zuordnung, festgeschriebene Stufe, Filterung in Web und Verwaltung.
 */
class GradingStufenFragenTest extends TestCase
{
    private GradingSystem $system;
    private GradingStage $stufe1;
    private GradingStage $stufe2;
    private GradingQuestion $alle;      // ohne Zuordnung = alle Stufen
    private GradingQuestion $nur1;
    private GradingQuestion $beide;     // Stufe 1 und 2
    private GradingQuestion $nur2;
    private Klasse $klasse;
    private Schueler $anna;   // Stufe 1
    private Schueler $ben;    // Stufe 2
    private Schueler $carla;  // ohne Stufe
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->system = GradingSystem::factory()->create();
        $this->stufe1 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Stufe Eins', 'sort_order' => 1]);
        $this->stufe2 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Stufe Zwei', 'sort_order' => 2]);

        $this->alle = $this->frage('Frage für alle', 1);
        $this->nur1 = $this->frage('Frage nur Stufe eins', 2, [$this->stufe1]);
        $this->beide = $this->frage('Frage beide Stufen', 3, [$this->stufe1, $this->stufe2]);
        $this->nur2 = $this->frage('Frage nur Stufe zwei', 4, [$this->stufe2]);

        $this->user = $this->actingAsWithPermission('view paed diary');
        $this->klasse = Klasse::factory()->create(['grading_system_id' => $this->system->id]);
        $this->user->paed_klassen()->attach($this->klasse->id);
        $this->anna = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Anna', 'nachname' => 'A', 'grading_stage_id' => $this->stufe1->id]);
        $this->ben = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Ben', 'nachname' => 'B', 'grading_stage_id' => $this->stufe2->id]);
        $this->carla = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Carla', 'nachname' => 'C', 'grading_stage_id' => null]);
    }

    private function frage(string $text, int $sort, array $stufen = []): GradingQuestion
    {
        $frage = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => $text, 'sort_order' => $sort, 'active' => true]);
        $frage->stages()->sync(collect($stufen)->pluck('id'));

        return $frage;
    }

    private function sitzung(array $attribute = []): GradingDocumentationSession
    {
        return GradingDocumentationSession::factory()->asGroup()->create(array_merge([
            'user_id' => $this->user->id,
            'klasse_id' => $this->klasse->id,
            'grading_system_id' => $this->system->id,
        ], $attribute));
    }

    private function service(): GradingSessionService
    {
        return app(GradingSessionService::class);
    }

    private function token(GradingDocumentationSession $session, Schueler $schueler): string
    {
        $token = Str::random(64);
        DB::table('grading_student_tokens')->insert([
            'token' => $token, 'session_id' => $session->id, 'schueler_id' => $schueler->id,
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    /** @test */
    public function jeder_schueler_bekommt_die_fragen_seiner_stufe(): void
    {
        $fragen = $this->service()->fragenJeSchueler($this->sitzung());

        $this->assertSame([$this->alle->id, $this->nur1->id, $this->beide->id], $fragen[$this->anna->id]->all());
        $this->assertSame([$this->alle->id, $this->beide->id, $this->nur2->id], $fragen[$this->ben->id]->all());
        // Ohne Stufe nur die Fragen für alle Stufen
        $this->assertSame([$this->alle->id], $fragen[$this->carla->id]->all());
    }

    /** @test */
    public function inaktive_fragen_werden_nicht_gestellt(): void
    {
        $this->nur1->update(['active' => false]);

        $this->assertSame([$this->alle->id, $this->beide->id], $this->service()->fragenFuerSchueler($this->sitzung(), $this->anna->id)->all());
    }

    /** @test */
    public function fortschritt_zaehlt_die_fragen_je_schueler(): void
    {
        $session = $this->sitzung();
        GradingTeacherAssessment::create(['session_id' => $session->id, 'schueler_id' => $this->carla->id, 'question_id' => $this->alle->id, 'teacher_rating' => 3, 'assessed_at' => now()]);

        $progress = $this->service()->progress($session);

        $this->assertSame(3 + 3 + 1, $progress['total']);
        $this->assertSame(1, $progress['answered']);
    }

    /** @test */
    public function fremde_frage_wird_abgelehnt_und_stufe_beim_ersten_speichern_festgeschrieben(): void
    {
        $session = $this->sitzung();

        $this->postJson(route('gradingDocumentation.saveTeacherAssessment'), [
            'session_id' => $session->id, 'schueler_id' => $this->anna->id, 'question_id' => $this->nur2->id, 'teacher_rating' => 4,
        ])->assertStatus(422)->assertJsonPath('message', 'Die Frage gilt nicht für die Graduierungsstufe dieses Schülers.');
        $this->assertDatabaseCount('grading_session_stages', 0);

        $this->postJson(route('gradingDocumentation.saveStudentAnswer'), [
            'session_id' => $session->id, 'schueler_id' => $this->anna->id, 'question_id' => $this->nur1->id, 'self_rating' => 4,
        ])->assertOk();

        $this->assertDatabaseHas('grading_session_stages', [
            'session_id' => $session->id, 'schueler_id' => $this->anna->id, 'grading_stage_id' => $this->stufe1->id,
        ]);
    }

    /** @test */
    public function stufenkorrektur_wird_nur_vor_der_ersten_antwort_uebernommen(): void
    {
        $session = $this->sitzung();

        // Noch nichts beantwortet: die Session folgt der korrigierten Stufe
        $this->anna->update(['grading_stage_id' => $this->stufe2->id]);
        $this->assertTrue($this->service()->fragenFuerSchueler($session, $this->anna->id)->contains($this->nur2->id));

        // Nach der ersten Antwort bleibt die Stufe der Session fest
        $this->postJson(route('gradingDocumentation.saveStudentAnswer'), [
            'session_id' => $session->id, 'schueler_id' => $this->anna->id, 'question_id' => $this->nur2->id, 'self_rating' => 2,
        ])->assertOk();
        $this->anna->update(['grading_stage_id' => $this->stufe1->id]);

        $fragen = $this->service()->fragenFuerSchueler($session, $this->anna->id);
        $this->assertTrue($fragen->contains($this->nur2->id));
        $this->assertFalse($fragen->contains($this->nur1->id));
    }

    /** @test */
    public function abschluss_schreibt_die_stufe_aller_teilnehmer_fest(): void
    {
        $session = $this->sitzung();

        $this->service()->abschliessen($session, $this->user);

        $this->assertSame(3, GradingSessionStage::where('session_id', $session->id)->count());
        $this->assertDatabaseHas('grading_session_stages', ['session_id' => $session->id, 'schueler_id' => $this->carla->id, 'grading_stage_id' => null]);
    }

    /** @test */
    public function beantwortete_fragen_bleiben_nach_entzug_der_zuordnung_sichtbar(): void
    {
        $session = $this->sitzung();
        GradingStudentAnswer::create(['session_id' => $session->id, 'schueler_id' => $this->anna->id, 'question_id' => $this->nur1->id, 'self_rating' => 4, 'answered_at' => now()]);
        $this->service()->stufeFestschreiben($session, $this->anna->id);

        $this->nur1->stages()->sync([$this->stufe2->id]);

        $this->assertTrue($this->service()->fragenFuerSchueler($session, $this->anna->id)->contains($this->nur1->id));
    }

    /** @test */
    public function verlauf_zeigt_nur_die_fragen_des_schuelers_altbestand_alle(): void
    {
        $neu = $this->sitzung(['completed_at' => now()]);
        GradingSessionStage::create(['session_id' => $neu->id, 'schueler_id' => $this->anna->id, 'grading_stage_id' => $this->stufe1->id]);
        $alt = $this->sitzung(['completed_at' => now()->subMonth()]);

        $sessions = app(StudentDataService::class)->completedGradingSessions($this->anna)->keyBy('id');

        $this->assertSame([$this->alle->id, $this->nur1->id, $this->beide->id], $sessions[$neu->id]->gradingSystem->questions->pluck('id')->all());
        // Vor der Stufenbindung galten alle Fragen
        $this->assertCount(4, $sessions[$alt->id]->gradingSystem->questions);
        // Jede Session hat ihre eigene Kopie des Systems
        $this->assertNotSame($sessions[$neu->id]->gradingSystem, $sessions[$alt->id]->gradingSystem);
    }

    /** @test */
    public function gruppenansicht_liefert_fragen_je_schueler(): void
    {
        $this->user->forceFill(['paed_diary_darstellung' => ['ansicht' => 'neu', 'bewertung' => 'schuelerweise']])->save();
        $session = $this->sitzung();

        $response = $this->get(route('gradingDocumentation.groupSession', $session))->assertOk();

        $fragenJeSchueler = $response->viewData('fragenJeSchueler');
        $this->assertSame([$this->alle->id], $fragenJeSchueler[$this->carla->id]->all());
        $this->assertCount(4, $response->viewData('questions'));
        $response->assertSee('Stufe Eins')->assertSee('ohne Stufe');
    }

    /** @test */
    public function oeffentliche_seite_ohne_fragen_fuer_die_stufe(): void
    {
        $this->alle->update(['active' => false]);
        $session = $this->sitzung();

        $this->get(route('gradingDocumentation.publicStudentSession', $this->token($session, $this->carla)))
            ->assertOk()
            ->assertSee('Für dich gibt es heute keine Fragen');

        $this->get(route('gradingDocumentation.publicStudentSession', $this->token($session, $this->anna)))
            ->assertOk()
            ->assertSee(json_encode('Frage nur Stufe eins'), false)
            ->assertDontSee(json_encode('Frage nur Stufe zwei'), false);
    }

    /** @test */
    public function oeffentliche_seite_ueberspringt_freigegebene_fragen_anderer_stufen(): void
    {
        $session = $this->sitzung(['answer_order_mode' => 'by_question', 'current_question_id' => $this->nur2->id]);
        GradingStudentAnswer::create(['session_id' => $session->id, 'schueler_id' => $this->carla->id, 'question_id' => $this->alle->id, 'self_rating' => 3, 'answered_at' => now()]);

        $questions = $this->service()->releasedQuestions($session, $this->anna->id);
        $this->assertSame([$this->alle->id, $this->nur1->id, $this->beide->id], $questions->pluck('id')->all());
        $this->assertSame([$this->alle->id], $this->service()->releasedQuestions($session, $this->carla->id)->pluck('id')->all());
    }

    // ── Verwaltung ─────────────────────────────────────────────────────

    /** @test */
    public function verwaltung_ordnet_fragen_stufen_zu(): void
    {
        $this->actingAsWithPermission('view paed diary', 'manage grading systems');
        $fremdeStufe = GradingStage::create(['grading_system_id' => GradingSystem::factory()->create()->id, 'name' => 'Fremd', 'sort_order' => 1]);

        $this->post(route('admin.grading.question.store', $this->system), [
            'question' => 'Neue Frage', 'stage_ids' => [$this->stufe1->id, $this->stufe2->id],
        ])->assertRedirect();
        $neu = GradingQuestion::where('question', 'Neue Frage')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->stufe1->id, $this->stufe2->id], $neu->stages()->pluck('grading_stages.id')->all());

        // Ohne Auswahl: alle Stufen
        $this->post(route('admin.grading.question.update', $neu), ['question' => 'Neue Frage', 'active' => 1])->assertRedirect();
        $this->assertSame(0, $neu->stages()->count());

        // Stufen anderer Systeme werden abgelehnt
        $this->post(route('admin.grading.question.update', $neu), ['question' => 'Neue Frage', 'active' => 1, 'stage_ids' => [$fremdeStufe->id]])
            ->assertSessionHasErrors('stage_ids.0');
    }

    /** @test */
    public function stufe_loeschen_deaktiviert_fragen_nur_dieser_stufe(): void
    {
        $this->actingAsWithPermission('view paed diary', 'manage grading systems');
        $this->ben->update(['grading_stage_id' => null]);

        $this->post(route('admin.grading.stage.delete', $this->stufe2))
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Frage nur Stufe zwei'));

        $this->assertFalse($this->nur2->fresh()->active);
        $this->assertTrue($this->beide->fresh()->active);
        $this->assertSame([$this->stufe1->id], $this->beide->stages()->pluck('grading_stages.id')->all());
    }
}
