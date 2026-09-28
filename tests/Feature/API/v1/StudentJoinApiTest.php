<?php

namespace Tests\Feature\API\v1;

use App\Models\GradingDocumentationSession;
use App\Models\GradingJoinCode;
use App\Models\GradingQuestion;
use App\Models\GradingStudentAnswer;
use App\Models\GradingSystem;
use App\Models\GradingTeacherAssessment;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * B5 – Selbsteinschätzung auf Schüler-iPads (Beitritt per QR/Code).
 */
class StudentJoinApiTest extends ApiTestCase
{
    private GradingSystem $system;
    private Klasse $klasse;
    private Schueler $anna;
    private Schueler $ben;
    private GradingQuestion $q1;
    private GradingQuestion $q2;
    private User $teacher;
    private GradingDocumentationSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = GradingSystem::factory()->create();
        $this->klasse = Klasse::factory()->create(['grading_system_id' => $this->system->id]);
        $this->anna = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Anna', 'nachname' => 'Arndt']);
        $this->ben = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Ben', 'nachname' => 'Böhm']);
        $this->q1 = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Ich arbeite selbstständig', 'sort_order' => 1, 'active' => true]);
        $this->q2 = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Ich helfe anderen', 'sort_order' => 2, 'active' => true]);

        $this->teacher = $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $id = $this->postJson(self::API . '/grading/sessions', [
            'type' => 'group', 'class_id' => $this->klasse->id, 'schueler_ids' => [$this->anna->id, $this->ben->id],
        ])->assertCreated()->json('data.id');
        $this->session = GradingDocumentationSession::findOrFail($id);
    }

    /** @return array<int, array> Codes je schueler_id */
    private function createCodes(): array
    {
        Sanctum::actingAs($this->teacher, ['paed-app']);

        return collect($this->postJson(self::API . "/grading/sessions/{$this->session->id}/join-codes")->assertOk()->json('data'))
            ->keyBy('schueler_id')->all();
    }

    private function join(string $code, string $device = 'iPad 07')
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => ''])->postJson(self::API . '/student/join', ['code' => $code, 'device_name' => $device]);
    }

    private function asStudent(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /** @test */
    public function lehrkraft_erzeugt_codes_je_schueler(): void
    {
        $codes = $this->createCodes();

        $this->assertCount(2, $codes);
        $anna = $codes[$this->anna->id];
        $this->assertSame('Anna', $anna['firstname']);
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{3}-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{3}$/', $anna['code']);
        $this->assertSame(
            'paeddiary://join?server=' . rtrim(url('/'), '/') . '&code=' . str_replace('-', '', $anna['code']),
            $anna['qr_payload']
        );
        $this->assertEqualsWithDelta(now()->addHours(8)->getTimestamp(), strtotime($anna['expires_at']), 5);

        // Erneuter Aufruf liefert dieselben gültigen Codes
        $this->assertSame($anna['code'], $this->createCodes()[$this->anna->id]['code']);
    }

    /** @test */
    public function nur_ersteller_darf_codes_verwalten(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);

        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/join-codes")->assertForbidden();
        $this->deleteJson(self::API . "/grading/sessions/{$this->session->id}/join-codes")->assertForbidden();
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/current-question", ['question_id' => $this->q1->id])->assertForbidden();
    }

    /** @test */
    public function schueler_tritt_bei_und_gibt_selbsteinschaetzung_ab(): void
    {
        $code = $this->createCodes()[$this->anna->id]['code'];

        // Groß-/Kleinschreibung und Bindestrich egal
        $join = $this->join(strtolower(str_replace('-', '', $code)))
            ->assertCreated()
            ->assertJsonPath('student.firstname', 'Anna')
            ->assertJsonPath('session.id', $this->session->id)
            ->assertJsonPath('session.answer_order_mode', 'by_student')
            ->assertJsonMissingPath('student.lastname');
        $token = $join->json('token');

        $this->assertSame(
            ["student-grading:{$this->session->id}:{$this->anna->id}"],
            PersonalAccessToken::findToken($token)->abilities
        );

        $this->asStudent($token)->getJson(self::API . '/student/session')
            ->assertOk()
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonPath('data.waiting', false)
            ->assertJsonPath('data.self_ratings', []);

        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->q1->id, 'self_rating' => 4])
            ->assertOk()->assertJsonPath('data.self_rating', 4);

        $this->asStudent($token)->getJson(self::API . '/student/session')
            ->assertOk()->assertJsonPath('data.self_ratings.0.question_id', $this->q1->id)->assertJsonPath('data.self_ratings.0.self_rating', 4);

        // Lehrkraft sieht die Selbsteinschätzung in der Session
        Sanctum::actingAs($this->teacher, ['paed-app']);
        $answers = collect($this->getJson(self::API . "/grading/sessions/{$this->session->id}")->assertOk()->json('data.answers'));
        $this->assertSame(4, $answers->firstWhere('schueler_id', $this->anna->id)['self_rating']);
    }

    /** @test */
    public function schueler_kann_nur_self_rating_schreiben(): void
    {
        $token = $this->join($this->createCodes()[$this->anna->id]['code'])->json('token');

        $this->asStudent($token)->postJson(self::API . '/student/session/answers', [
            'question_id' => $this->q1->id, 'self_rating' => 5, 'rating_value' => 5, 'comment' => 'Super',
        ])->assertStatus(422)->assertJsonValidationErrors(['rating_value', 'comment']);

        $this->asStudent($token)->postJson(self::API . '/student/session/answers', [
            'question_id' => $this->q1->id, 'self_rating' => 5, 'schueler_id' => $this->ben->id,
        ])->assertStatus(422)->assertJsonValidationErrors('schueler_id');

        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->q1->id, 'self_rating' => 9])
            ->assertStatus(422)->assertJsonValidationErrors('self_rating');

        $fremd = GradingQuestion::create(['grading_system_id' => GradingSystem::factory()->create()->id, 'question' => 'x', 'sort_order' => 1, 'active' => true]);
        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $fremd->id, 'self_rating' => 3])
            ->assertStatus(422)->assertJsonValidationErrors('question_id');

        $this->assertSame(0, GradingTeacherAssessment::count());
        $this->assertSame(0, GradingStudentAnswer::where('schueler_id', $this->ben->id)->count());
    }

    /** @test */
    public function schueler_token_kann_keine_anderen_endpunkte_aufrufen(): void
    {
        $token = $this->join($this->createCodes()[$this->anna->id]['code'])->json('token');

        $this->asStudent($token)->getJson(self::API . '/classes')->assertForbidden();
        $this->asStudent($token)->getJson(self::API . "/students/{$this->anna->id}/view")->assertForbidden();
        $this->asStudent($token)->getJson(self::API . "/students/{$this->anna->id}/paed-diary/entries")->assertForbidden();
        $this->asStudent($token)->getJson(self::API . "/grading/sessions/{$this->session->id}")->assertForbidden();
        $this->asStudent($token)->getJson(self::API . '/auth/me')->assertForbidden();

        // Umgekehrt: Lehrkraft-Token nicht auf Schüler-Endpunkten
        $teacherToken = $this->teacher->createToken('iPad', ['paed-app'])->plainTextToken;
        $this->asStudent($teacherToken)->getJson(self::API . '/student/session')->assertForbidden();
    }

    /** @test */
    public function by_question_liefert_nur_freigegebene_fragen(): void
    {
        Sanctum::actingAs($this->teacher, ['paed-app']);
        $this->patchJson(self::API . "/grading/sessions/{$this->session->id}", ['answer_order_mode' => 'by_question'])->assertOk();
        $token = $this->join($this->createCodes()[$this->anna->id]['code'])
            ->assertJsonPath('session.answer_order_mode', 'by_question')->json('token');

        // Noch keine Frage freigegeben → warten
        $this->asStudent($token)->getJson(self::API . '/student/session')
            ->assertOk()->assertJsonCount(0, 'data.questions')->assertJsonPath('data.waiting', true);
        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->q1->id, 'self_rating' => 3])
            ->assertStatus(422);

        Sanctum::actingAs($this->teacher, ['paed-app']);
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/current-question", ['question_id' => $this->q1->id])
            ->assertOk()->assertJsonPath('data.current_question_id', $this->q1->id);

        $this->asStudent($token)->getJson(self::API . '/student/session')
            ->assertOk()->assertJsonCount(1, 'data.questions')->assertJsonPath('data.questions.0.id', $this->q1->id)
            ->assertJsonPath('data.waiting', false);
        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->q2->id, 'self_rating' => 3])
            ->assertStatus(422)->assertJsonValidationErrors('question_id');
        $this->asStudent($token)->postJson(self::API . '/student/session/answers', ['question_id' => $this->q1->id, 'self_rating' => 3])
            ->assertOk();
        $this->asStudent($token)->getJson(self::API . '/student/session')->assertJsonPath('data.waiting', true);

        // Freigabe nur im Modus by_question und nur für Fragen des Systems
        Sanctum::actingAs($this->teacher, ['paed-app']);
        $fremd = GradingQuestion::create(['grading_system_id' => GradingSystem::factory()->create()->id, 'question' => 'x', 'sort_order' => 1, 'active' => true]);
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/current-question", ['question_id' => $fremd->id])
            ->assertStatus(422)->assertJsonValidationErrors('question_id');
        $this->patchJson(self::API . "/grading/sessions/{$this->session->id}", ['answer_order_mode' => 'by_student'])->assertOk();
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/current-question", ['question_id' => $this->q2->id])
            ->assertStatus(422);
    }

    /** @test */
    public function widerruf_macht_tokens_ungueltig(): void
    {
        $codes = $this->createCodes();
        $token = $this->join($codes[$this->anna->id]['code'])->json('token');

        Sanctum::actingAs($this->teacher, ['paed-app']);
        $this->deleteJson(self::API . "/grading/sessions/{$this->session->id}/join-codes")->assertNoContent();

        $this->asStudent($token)->getJson(self::API . '/student/session')->assertUnauthorized();
        $this->join($codes[$this->ben->id]['code'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertSame(0, GradingJoinCode::count());
    }

    /** @test */
    public function abgeschlossene_session_macht_tokens_ungueltig(): void
    {
        $codes = $this->createCodes();
        $token = $this->join($codes[$this->anna->id]['code'])->json('token');

        $this->session->update(['completed_at' => now()]);

        $this->asStudent($token)->getJson(self::API . '/student/session')->assertUnauthorized();
        $this->join($codes[$this->ben->id]['code'])->assertStatus(422);

        Sanctum::actingAs($this->teacher, ['paed-app']);
        $this->postJson(self::API . "/grading/sessions/{$this->session->id}/join-codes")->assertStatus(409);
    }

    /** @test */
    public function codes_laufen_nach_8_stunden_ab(): void
    {
        $codes = $this->createCodes();
        $token = $this->join($codes[$this->anna->id]['code'])->json('token');

        $this->travel(8)->hours();
        $this->travel(1)->minutes();

        $this->asStudent($token)->getJson(self::API . '/student/session')->assertUnauthorized();
        $this->join($codes[$this->ben->id]['code'])->assertStatus(422);

        $this->artisan('paed-app:prune-idempotency')->assertSuccessful();
        $this->assertSame(0, GradingJoinCode::count());
    }

    /** @test */
    public function erneuter_beitritt_ersetzt_das_alte_geraet(): void
    {
        $code = $this->createCodes()[$this->anna->id]['code'];
        $old = $this->join($code, 'iPad 07')->json('token');
        $new = $this->join($code, 'iPad 08')->json('token');

        $this->asStudent($old)->getJson(self::API . '/student/session')->assertUnauthorized();
        $this->asStudent($new)->getJson(self::API . '/student/session')->assertOk();
    }

    /** @test */
    public function ungueltiger_code_und_rate_limit(): void
    {
        $this->join('')->assertStatus(422)->assertJsonValidationErrors('code');

        for ($i = 1; $i < 10; $i++) {
            $this->join('ABC-DEF')->assertStatus(422);
        }

        $this->join('ABC-DEF')->assertStatus(429);
    }
}
