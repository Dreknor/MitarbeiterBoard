<?php

namespace Tests\Feature\API\v1;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;

class GradingApiTest extends ApiTestCase
{
    private GradingSystem $system;
    private GradingStage $stage1;
    private GradingStage $stage2;
    private Klasse $klasse;
    private Schueler $schueler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = GradingSystem::factory()->create();
        $this->stage1 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Graduierung I', 'slug' => 'g1', 'sort_order' => 1]);
        $this->stage2 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Graduierung II', 'slug' => 'g2', 'sort_order' => 2]);
        $this->klasse = Klasse::factory()->create(['grading_system_id' => $this->system->id]);
        $this->schueler = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'grading_stage_id' => $this->stage1->id]);
    }

    private function question(string $text = 'Frage', ?int $systemId = null): GradingQuestion
    {
        return GradingQuestion::create([
            'grading_system_id' => $systemId ?? $this->system->id,
            'question' => $text,
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    /** @test */
    public function stufenkatalog_wird_geliefert(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);

        $this->getJson(self::API . '/grading/stages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Graduierung I')
            ->assertJsonPath('data.1.level', 2)
            ->assertJsonPath('meta.grading_systems.0.id', $this->system->id);

        $this->getJson(self::API . "/grading/stages?class_id={$this->klasse->id}")->assertOk()->assertJsonCount(2, 'data');
    }

    /** @test */
    public function session_starten_und_fortsetzen(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $this->question('Arbeitet eigenverantwortlich');

        $first = $this->postJson(self::API . '/grading/sessions', ['schueler_id' => $this->schueler->id])
            ->assertCreated()
            ->assertJsonPath('meta.resumed', false)
            ->assertJsonPath('data.type', 'individual')
            ->assertJsonPath('data.schueler_id', $this->schueler->id)
            ->assertJsonPath('data.questions.0.question', 'Arbeitet eigenverantwortlich');

        $this->postJson(self::API . '/grading/sessions', ['schueler_id' => $this->schueler->id])
            ->assertOk()
            ->assertJsonPath('meta.resumed', true)
            ->assertJsonPath('data.id', $first->json('data.id'));
    }

    /** @test */
    public function session_ohne_graduierungssystem_liefert_422(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->postJson(self::API . '/grading/sessions', ['schueler_id' => $schueler->id])
            ->assertStatus(422)->assertJsonValidationErrors('schueler_id');
    }

    /** @test */
    public function bewertungen_speichern_und_zwischenstand_abrufen(): void
    {
        $user = $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $q1 = $this->question('F1');
        $q2 = $this->question('F2');
        $session = GradingDocumentationSession::factory()->create([
            'klasse_id' => $this->klasse->id, 'grading_system_id' => $this->system->id,
            'schueler_id' => $this->schueler->id, 'user_id' => $user->id,
        ]);

        $this->postJson(self::API . "/grading/sessions/{$session->id}/assessments", [
            'answers' => [
                ['question_id' => $q1->id, 'rating_value' => 4, 'comment' => 'Sehr eigenverantwortlich.'],
                ['question_id' => $q2->id, 'rating_value' => 3, 'comment' => null, 'self_rating' => 5],
            ],
            'teacher_assessment' => 'Kriterien weitgehend erfüllt.',
        ])->assertOk()->assertJsonPath('meta.finalized', false);

        $this->assertDatabaseHas('grading_teacher_assessments', ['session_id' => $session->id, 'question_id' => $q1->id, 'teacher_rating' => 4]);
        $this->assertDatabaseHas('grading_student_answers', ['session_id' => $session->id, 'question_id' => $q2->id, 'self_rating' => 5]);
        $this->assertDatabaseHas('grading_coaching_notes', ['session_id' => $session->id, 'note' => 'Kriterien weitgehend erfüllt.']);

        $response = $this->getJson(self::API . "/grading/sessions/{$session->id}")->assertOk();
        $answers = collect($response->json('data.answers'))->keyBy('question_id');
        $this->assertSame(4, $answers[$q1->id]['rating_value']);
        $this->assertSame(5, $answers[$q2->id]['self_rating']);
        $response->assertJsonPath('data.teacher_assessments.0.note', 'Kriterien weitgehend erfüllt.')
            ->assertJsonCount(2, 'meta.available_stages');
    }

    /** @test */
    public function abschluss_mit_stufenvergabe_erzeugt_historie(): void
    {
        $user = $this->actingAsTeacher(['view paed diary', 'manage grading systems'], [$this->klasse]);
        $q1 = $this->question();
        $session = GradingDocumentationSession::factory()->create([
            'klasse_id' => $this->klasse->id, 'grading_system_id' => $this->system->id,
            'schueler_id' => $this->schueler->id, 'user_id' => $user->id,
        ]);

        $this->postJson(self::API . "/grading/sessions/{$session->id}/assessments", [
            'answers' => [['question_id' => $q1->id, 'rating_value' => 5]],
            'teacher_assessment' => 'Die Kriterien für Graduierung II sind erfüllt.',
            'finalize' => true,
            'grading_stage_id' => $this->stage2->id,
        ])->assertOk()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('meta.current_stage.title', 'Graduierung II');

        $this->assertSame($this->stage2->id, $this->schueler->fresh()->grading_stage_id);
        $this->assertDatabaseHas('schueler_grading_histories', [
            'schueler_id' => $this->schueler->id,
            'grading_stage_id' => $this->stage2->id,
            'previous_grading_stage_id' => $this->stage1->id,
            'changed_by' => $user->id,
        ]);

        // Historie über die API
        $this->getJson(self::API . "/students/{$this->schueler->id}/grading/history")
            ->assertOk()
            ->assertJsonPath('data.current_stage.title', 'Graduierung II')
            ->assertJsonPath('data.stage_history.0.previous_stage_title', 'Graduierung I')
            ->assertJsonPath('data.completed_sessions.0.id', $session->id);

        // Abgeschlossene Session kann nicht mehr bearbeitet werden
        $this->postJson(self::API . "/grading/sessions/{$session->id}/assessments", [
            'answers' => [['question_id' => $q1->id, 'rating_value' => 1]],
        ])->assertStatus(409);
    }

    /** @test */
    public function stufenvergabe_ohne_recht_liefert_403_und_speichert_nichts(): void
    {
        $user = $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $session = GradingDocumentationSession::factory()->create([
            'klasse_id' => $this->klasse->id, 'grading_system_id' => $this->system->id,
            'schueler_id' => $this->schueler->id, 'user_id' => $user->id,
        ]);

        $this->postJson(self::API . "/grading/sessions/{$session->id}/assessments", [
            'finalize' => true,
            'grading_stage_id' => $this->stage2->id,
        ])->assertForbidden();

        $this->assertNull($session->fresh()->completed_at);
        $this->assertSame($this->stage1->id, $this->schueler->fresh()->grading_stage_id);
    }

    /** @test */
    public function validierung_der_bewertungen(): void
    {
        $user = $this->actingAsTeacher(['view paed diary', 'manage grading systems'], [$this->klasse]);
        $fremdesSystem = GradingSystem::factory()->create();
        $fremdeFrage = $this->question('Fremd', $fremdesSystem->id);
        $fremdeStufe = GradingStage::create(['grading_system_id' => $fremdesSystem->id, 'name' => 'X', 'slug' => 'x', 'sort_order' => 1]);
        $session = GradingDocumentationSession::factory()->create([
            'klasse_id' => $this->klasse->id, 'grading_system_id' => $this->system->id,
            'schueler_id' => $this->schueler->id, 'user_id' => $user->id,
        ]);
        $url = self::API . "/grading/sessions/{$session->id}/assessments";

        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors('answers');
        $this->postJson($url, ['answers' => [['question_id' => 999999, 'rating_value' => 9]]])
            ->assertStatus(422)->assertJsonValidationErrors(['answers.0.question_id', 'answers.0.rating_value']);
        $this->postJson($url, ['answers' => [['question_id' => $fremdeFrage->id, 'rating_value' => 3]]])
            ->assertStatus(422)->assertJsonValidationErrors('answers');
        $this->postJson($url, ['grading_stage_id' => $this->stage2->id, 'teacher_assessment' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('grading_stage_id');
        $this->postJson($url, ['finalize' => true, 'grading_stage_id' => $fremdeStufe->id])
            ->assertStatus(422)->assertJsonValidationErrors('grading_stage_id');
    }

    /** @test */
    public function fremde_session_darf_nicht_bearbeitet_werden(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $q1 = $this->question();
        $session = GradingDocumentationSession::factory()->create([
            'klasse_id' => $this->klasse->id, 'grading_system_id' => $this->system->id,
            'schueler_id' => $this->schueler->id, 'user_id' => User::factory()->create()->id,
        ]);

        // Ansehen ist über die Klassenzuordnung erlaubt, Bearbeiten nur für den Ersteller
        $this->getJson(self::API . "/grading/sessions/{$session->id}")->assertOk()->assertJsonPath('data.is_owner', false);
        $this->postJson(self::API . "/grading/sessions/{$session->id}/assessments", [
            'answers' => [['question_id' => $q1->id, 'rating_value' => 3]],
        ])->assertForbidden();
    }
}
