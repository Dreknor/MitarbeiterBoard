<?php

namespace Tests\Feature\API\v1;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * API: Graduierungsfragen je Stufe (Lehrkraft-App und Schüler-iPads).
 */
class GradingStufenFragenApiTest extends ApiTestCase
{
    private GradingSystem $system;
    private GradingStage $stufe1;
    private GradingStage $stufe2;
    private GradingQuestion $alle;
    private GradingQuestion $nur1;
    private GradingQuestion $nur2;
    private Klasse $klasse;
    private Schueler $anna;   // Stufe 1
    private Schueler $ben;    // Stufe 2
    private User $teacher;
    private GradingDocumentationSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = GradingSystem::factory()->create();
        $this->stufe1 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Stufe 1', 'sort_order' => 1]);
        $this->stufe2 = GradingStage::create(['grading_system_id' => $this->system->id, 'name' => 'Stufe 2', 'sort_order' => 2]);
        $this->alle = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Alle', 'sort_order' => 1, 'active' => true]);
        $this->nur1 = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Nur 1', 'sort_order' => 2, 'active' => true]);
        $this->nur1->stages()->sync([$this->stufe1->id]);
        $this->nur2 = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Nur 2', 'sort_order' => 3, 'active' => true]);
        $this->nur2->stages()->sync([$this->stufe2->id]);

        $this->klasse = Klasse::factory()->create(['grading_system_id' => $this->system->id]);
        $this->anna = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Anna', 'nachname' => 'A', 'grading_stage_id' => $this->stufe1->id]);
        $this->ben = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Ben', 'nachname' => 'B', 'grading_stage_id' => $this->stufe2->id]);

        $this->teacher = $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $id = $this->postJson(self::API . '/grading/sessions', [
            'type' => 'group', 'class_id' => $this->klasse->id, 'schueler_ids' => [$this->anna->id, $this->ben->id],
            'answer_order_mode' => 'by_question',
        ])->assertCreated()->json('data.id');
        $this->session = GradingDocumentationSession::findOrFail($id);
    }

    private function studentToken(Schueler $schueler): string
    {
        Sanctum::actingAs($this->teacher, ['paed-app']);
        $code = collect($this->postJson(self::API . "/grading/sessions/{$this->session->id}/join-codes")->assertOk()->json('data'))
            ->firstWhere('schueler_id', $schueler->id)['code'];

        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => ''])
            ->postJson(self::API . '/student/join', ['code' => $code, 'device_name' => 'iPad'])
            ->assertCreated()->json('token');
    }

    private function asStudent(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /** @test */
    public function session_liefert_stufen_und_fragen_je_schueler(): void
    {
        $response = $this->getJson(self::API . "/grading/sessions/{$this->session->id}")->assertOk();

        $fragen = collect($response->json('data.questions'))->keyBy('id');
        $this->assertSame([], $fragen[$this->alle->id]['stage_ids']);
        $this->assertSame(2, $fragen[$this->alle->id]['student_count']);
        $this->assertSame([$this->stufe1->id], $fragen[$this->nur1->id]['stage_ids']);
        $this->assertSame(1, $fragen[$this->nur1->id]['student_count']);

        $schueler = collect($response->json('meta.students'))->keyBy('id');
        $this->assertSame($this->stufe1->id, $schueler[$this->anna->id]['grading_stage_id']);
        $this->assertSame([$this->alle->id, $this->nur1->id], $schueler[$this->anna->id]['question_ids']);
        $this->assertSame([$this->alle->id, $this->nur2->id], $schueler[$this->ben->id]['question_ids']);
    }

    /** @test */
    public function bewertung_fremder_fragen_wird_abgelehnt(): void
    {
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/assessments", [
            'schueler_id' => $this->anna->id,
            'answers' => [['question_id' => $this->nur2->id, 'rating_value' => 3]],
        ])->assertStatus(422)->assertJsonValidationErrors('answers');

        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/assessments", [
            'schueler_id' => $this->anna->id,
            'answers' => [['question_id' => $this->nur1->id, 'rating_value' => 3]],
        ])->assertOk();

        $this->assertDatabaseHas('grading_session_stages', [
            'session_id' => $this->session->id, 'schueler_id' => $this->anna->id, 'grading_stage_id' => $this->stufe1->id,
        ]);
    }

    /** @test */
    public function freigabe_einer_frage_anderer_stufe_laesst_den_schueler_warten(): void
    {
        $token = $this->studentToken($this->anna);

        Sanctum::actingAs($this->teacher, ['paed-app']);
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/current-question", ['question_id' => $this->nur1->id])->assertOk();

        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->alle->id, 'self_rating' => 4])->assertOk();
        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->nur1->id, 'self_rating' => 3])->assertOk();

        // Freigabe einer Frage nur für Stufe 2: Anna wartet, darf sie nicht beantworten
        Sanctum::actingAs($this->teacher, ['paed-app']);
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/current-question", ['question_id' => $this->nur2->id])->assertOk();

        $this->asStudent($token)->getJson(self::API . '/student/session')
            ->assertOk()
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonPath('data.waiting', true)
            ->assertJsonPath('data.no_questions', false);

        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->nur2->id, 'self_rating' => 4])
            ->assertStatus(422);
    }

    /** @test */
    public function schueler_ohne_fragen_ist_fertig(): void
    {
        $this->alle->update(['active' => false]);
        $this->nur1->stages()->sync([$this->stufe2->id]);
        $token = $this->studentToken($this->anna);

        $this->asStudent($token)->getJson(self::API . '/student/session')
            ->assertOk()
            ->assertJsonCount(0, 'data.questions')
            ->assertJsonPath('data.waiting', false)
            ->assertJsonPath('data.no_questions', true);
    }
}
