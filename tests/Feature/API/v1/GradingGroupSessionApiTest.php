<?php

namespace Tests\Feature\API\v1;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;

/**
 * B4 – Gruppen-Graduierungssessions in der API.
 */
class GradingGroupSessionApiTest extends ApiTestCase
{
    private GradingSystem $system;
    private GradingStage $stage1;
    private GradingStage $stage2;
    private Klasse $klasse;
    private Schueler $anna;
    private Schueler $ben;
    private Schueler $carla;
    private GradingQuestion $q1;
    private GradingQuestion $q2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = GradingSystem::factory()->create();
        $this->stage1 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Graduierung I', 'slug' => 'g1', 'sort_order' => 1]);
        $this->stage2 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Graduierung II', 'slug' => 'g2', 'sort_order' => 2]);
        $this->klasse = Klasse::factory()->create(['grading_system_id' => $this->system->id]);
        $this->anna = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Anna', 'nachname' => 'Arndt', 'grading_stage_id' => $this->stage1->id]);
        $this->ben = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Ben', 'nachname' => 'Böhm', 'grading_stage_id' => $this->stage1->id]);
        $this->carla = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Carla', 'nachname' => 'Clausen']);
        $this->q1 = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Arbeitet selbstständig', 'sort_order' => 1, 'active' => true]);
        $this->q2 = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Hilft anderen', 'sort_order' => 2, 'active' => true]);
    }

    private function startGroup(array $payload = [])
    {
        return $this->postJson(self::API . '/grading/sessions', array_merge([
            'type' => 'group',
            'class_id' => $this->klasse->id,
            'schueler_ids' => [$this->anna->id, $this->ben->id],
            'answer_order_mode' => 'by_student',
        ], $payload));
    }

    /** @test */
    public function gruppensession_anlegen_in_beiden_modi_und_fortsetzen(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);

        $first = $this->startGroup(['answer_order_mode' => 'by_question'])
            ->assertCreated()
            ->assertJsonPath('meta.resumed', false)
            ->assertJsonPath('data.type', 'group')
            ->assertJsonPath('data.answer_order_mode', 'by_question')
            ->assertJsonPath('data.class_id', $this->klasse->id)
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonCount(2, 'meta.students')
            ->assertJsonPath('meta.students.0.firstname', 'Anna')
            ->assertJsonPath('meta.students.0.lastname_initial', 'A.')
            ->assertJsonPath('meta.students.0.finalized', false)
            ->assertJsonPath('meta.current_question_id', null);

        // Eigene offene Session für dieselbe Klasse wird fortgesetzt; ohne Modus bleibt er erhalten
        $this->startGroup(['answer_order_mode' => null, 'schueler_ids' => [$this->carla->id]])
            ->assertOk()
            ->assertJsonPath('meta.resumed', true)
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.answer_order_mode', 'by_question')
            ->assertJsonCount(3, 'meta.students');

        // Mit Modus wird er wie im Web übernommen
        $this->startGroup(['answer_order_mode' => 'by_student'])
            ->assertOk()->assertJsonPath('data.answer_order_mode', 'by_student');

        $this->assertSame(1, GradingDocumentationSession::where('type', 'group')->count());
    }

    /** @test */
    public function ohne_schueler_ids_umfasst_die_session_die_ganze_klasse(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);

        $this->postJson(self::API . '/grading/sessions', ['type' => 'group', 'class_id' => $this->klasse->id])
            ->assertCreated()
            ->assertJsonPath('data.answer_order_mode', 'by_student')
            ->assertJsonCount(3, 'meta.students');
    }

    /** @test */
    public function einzelsession_bleibt_unveraendert(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);

        $this->postJson(self::API . '/grading/sessions', ['schueler_id' => $this->anna->id])
            ->assertCreated()
            ->assertJsonPath('data.type', 'individual')
            ->assertJsonPath('data.schueler_id', $this->anna->id)
            ->assertJsonPath('meta.resumed', false)
            ->assertJsonCount(1, 'meta.students');
    }

    /** @test */
    public function validierung_und_fremde_klasse(): void
    {
        [$fremdeKlasse, $fremd] = $this->classWithStudent();
        $fremdeKlasse->update(['grading_system_id' => $this->system->id]);
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);

        $this->postJson(self::API . '/grading/sessions', ['type' => 'group'])
            ->assertStatus(422)->assertJsonValidationErrors('class_id');
        $this->startGroup(['schueler_ids' => [$this->anna->id, $fremd->id]])
            ->assertStatus(422)->assertJsonValidationErrors('schueler_ids');
        $this->startGroup(['answer_order_mode' => 'zufall'])
            ->assertStatus(422)->assertJsonValidationErrors('answer_order_mode');
        $this->startGroup(['class_id' => $fremdeKlasse->id, 'schueler_ids' => [$fremd->id]])
            ->assertForbidden();

        $ohneSystem = Klasse::factory()->create();
        $this->actingAsTeacher(['view paed diary'], [$ohneSystem]);
        $this->postJson(self::API . '/grading/sessions', ['type' => 'group', 'class_id' => $ohneSystem->id])
            ->assertStatus(422)->assertJsonValidationErrors('class_id');
    }

    /** @test */
    public function ohne_tagebuchrecht_403(): void
    {
        $this->actingAsTeacher([], [$this->klasse]);

        $this->startGroup()->assertForbidden();
        $this->getJson(self::API . "/classes/{$this->klasse->id}/grading/sessions")->assertForbidden();
    }

    /** @test */
    public function antworten_fuer_zwei_schueler_und_abschluss_je_schueler(): void
    {
        $user = $this->actingAsTeacher(['view paed diary', 'manage grading systems'], [$this->klasse]);
        $sessionId = $this->startGroup()->json('data.id');
        $url = self::API . "/grading/sessions/{$sessionId}/assessments";

        // schueler_id ist Pflicht, Nicht-Teilnehmer werden abgelehnt
        $this->postJson($url, ['answers' => [['question_id' => $this->q1->id, 'rating_value' => 3]]])
            ->assertStatus(422)->assertJsonValidationErrors('schueler_id');
        $this->postJson($url, ['schueler_id' => $this->carla->id, 'answers' => [['question_id' => $this->q1->id, 'rating_value' => 3]]])
            ->assertStatus(422)->assertJsonValidationErrors('schueler_id');

        foreach ([$this->anna, $this->ben] as $schueler) {
            $this->postJson($url, [
                'schueler_id' => $schueler->id,
                'answers' => [
                    ['question_id' => $this->q1->id, 'rating_value' => 4],
                    ['question_id' => $this->q2->id, 'rating_value' => 5, 'self_rating' => 4],
                ],
            ])->assertOk()->assertJsonPath('meta.finalized', false);
        }

        // Anna abschließen und Stufe vergeben – nur Anna ist abgeschlossen, die Session läuft weiter
        $this->postJson($url, [
            'schueler_id' => $this->anna->id,
            'teacher_assessment' => 'Kriterien für Graduierung II erfüllt.',
            'finalize' => true,
            'grading_stage_id' => $this->stage2->id,
        ])->assertOk()
            ->assertJsonPath('meta.finalized', true)
            ->assertJsonPath('meta.session_completed', false)
            ->assertJsonPath('data.is_completed', false)
            ->assertJsonPath('meta.current_stage.title', 'Graduierung II')
            ->assertJsonPath('meta.students.0.id', $this->anna->id)
            ->assertJsonPath('meta.students.0.finalized', true)
            ->assertJsonPath('meta.students.1.finalized', false);

        // Stufenwechsel + Historie + Tagebucheintrag wie bei der Einzelsession
        $this->assertSame($this->stage2->id, $this->anna->fresh()->grading_stage_id);
        $this->assertSame($this->stage1->id, $this->ben->fresh()->grading_stage_id);
        $this->assertDatabaseHas('schueler_grading_histories', [
            'schueler_id' => $this->anna->id,
            'grading_stage_id' => $this->stage2->id,
            'previous_grading_stage_id' => $this->stage1->id,
            'changed_by' => $user->id,
        ]);
        $this->assertDatabaseHas('paed_diary_entries', ['klasse_id' => $this->klasse->id, 'user_id' => $user->id]);

        // Abgeschlossener Schüler kann nicht erneut bewertet werden
        $this->postJson($url, ['schueler_id' => $this->anna->id, 'answers' => [['question_id' => $this->q1->id, 'rating_value' => 1]]])
            ->assertStatus(409);

        // Letzter Schüler → Session abgeschlossen
        $this->postJson($url, ['schueler_id' => $this->ben->id, 'finalize' => true])
            ->assertOk()
            ->assertJsonPath('meta.session_completed', true)
            ->assertJsonPath('data.is_completed', true);

        $this->assertNotNull(GradingDocumentationSession::find($sessionId)->completed_at);
    }

    /** @test */
    public function web_session_ohne_teilnehmerliste_wird_bei_abschluss_festgeschrieben(): void
    {
        $user = $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $session = GradingDocumentationSession::factory()->asGroup()->create([
            'klasse_id' => $this->klasse->id, 'grading_system_id' => $this->system->id, 'user_id' => $user->id,
        ]);
        $url = self::API . "/grading/sessions/{$session->id}/assessments";

        $this->postJson($url, ['schueler_id' => $this->carla->id, 'finalize' => true])
            ->assertOk()->assertJsonPath('meta.session_completed', false)->assertJsonCount(3, 'meta.students');

        $this->assertDatabaseCount('grading_session_students', 3);
        $this->postJson($url, ['schueler_id' => $this->anna->id, 'finalize' => true])->assertOk();
        $this->postJson($url, ['schueler_id' => $this->ben->id, 'finalize' => true])
            ->assertOk()->assertJsonPath('meta.session_completed', true);
    }

    /** @test */
    public function liste_zeigt_fortschritt_und_filtert_nach_status(): void
    {
        $user = $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $sessionId = $this->startGroup()->json('data.id');
        $this->postJson(self::API . "/grading/sessions/{$sessionId}/assessments", [
            'schueler_id' => $this->anna->id,
            'answers' => [['question_id' => $this->q1->id, 'rating_value' => 4], ['question_id' => $this->q2->id, 'rating_value' => 2]],
        ])->assertOk();

        GradingDocumentationSession::factory()->create([
            'klasse_id' => $this->klasse->id, 'grading_system_id' => $this->system->id,
            'schueler_id' => $this->ben->id, 'user_id' => User::factory()->create()->id, 'completed_at' => now(),
        ]);

        $this->getJson(self::API . "/classes/{$this->klasse->id}/grading/sessions?status=open")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sessionId)
            ->assertJsonPath('data.0.is_owner', true)
            ->assertJsonPath('data.0.progress.answered', 2)
            ->assertJsonPath('data.0.progress.total', 4)
            ->assertJsonPath('data.0.progress.students_total', 2)
            ->assertJsonMissingPath('data.0.questions')
            ->assertJsonMissingPath('data.0.answers');

        $this->getJson(self::API . "/classes/{$this->klasse->id}/grading/sessions?status=completed")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'individual');
        $this->getJson(self::API . "/classes/{$this->klasse->id}/grading/sessions?mine=true")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::API . "/classes/{$this->klasse->id}/grading/sessions?status=alle")
            ->assertStatus(422)->assertJsonValidationErrors('status');

        [$fremdeKlasse] = $this->classWithStudent();
        $this->getJson(self::API . "/classes/{$fremdeKlasse->id}/grading/sessions")->assertForbidden();
    }

    /** @test */
    public function modus_aendern_nur_ersteller_und_offene_session(): void
    {
        $user = $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $sessionId = $this->startGroup()->json('data.id');
        $url = self::API . "/grading/sessions/{$sessionId}";

        $this->patchJson($url, ['answer_order_mode' => 'by_question'])
            ->assertOk()->assertJsonPath('data.answer_order_mode', 'by_question');
        $this->patchJson($url, ['answer_order_mode' => 'egal'])
            ->assertStatus(422)->assertJsonValidationErrors('answer_order_mode');

        // Individuelle Sessions nur "by_student"
        $individual = $this->postJson(self::API . '/grading/sessions', ['schueler_id' => $this->anna->id])->json('data.id');
        $this->patchJson(self::API . "/grading/sessions/{$individual}", ['answer_order_mode' => 'by_question'])
            ->assertStatus(422)->assertJsonValidationErrors('answer_order_mode');

        // Andere Lehrkraft der Klasse darf ansehen, aber nicht ändern
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.is_owner', false);
        $this->patchJson($url, ['answer_order_mode' => 'by_student'])->assertForbidden();

        GradingDocumentationSession::whereKey($sessionId)->update(['completed_at' => now()]);
        \Laravel\Sanctum\Sanctum::actingAs($user, ['paed-app']);
        $this->patchJson($url, ['answer_order_mode' => 'by_student'])->assertStatus(409);
    }

    /** @test */
    public function sessiondetails_liefern_teilnehmer_und_freigegebene_frage(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $sessionId = $this->startGroup(['answer_order_mode' => 'by_question'])->json('data.id');

        $this->postJson(self::API . "/grading/sessions/{$sessionId}/current-question", ['question_id' => $this->q1->id])->assertOk();

        $this->getJson(self::API . "/grading/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('meta.current_question_id', $this->q1->id)
            ->assertJsonPath('data.current_question_id', $this->q1->id)
            ->assertJsonPath('meta.students.1.firstname', 'Ben')
            ->assertJsonPath('meta.students.1.lastname_initial', 'B.')
            ->assertJsonMissingPath('meta.students.1.lastname');
    }
}
