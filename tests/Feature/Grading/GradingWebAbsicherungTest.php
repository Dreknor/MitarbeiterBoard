<?php

namespace Tests\Feature\Grading;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingSessionStudent;
use App\Models\GradingStage;
use App\Models\GradingStudentAnswer;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\SchuelerGradingHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Graduierung (Web): Löschschutz, Schreibschutz abgeschlossener Sessions, QR-Zugang,
 * Wiederöffnen und die Stufenvergabe durch alle mit Zugriff auf den Schüler.
 */
class GradingWebAbsicherungTest extends TestCase
{
    private function systemMitFrage(): array
    {
        $system = GradingSystem::factory()->create();
        $frage = GradingQuestion::create(['grading_system_id' => $system->id, 'question' => 'Ich höre zu', 'sort_order' => 1, 'active' => true]);

        return [$system, $frage];
    }

    /** @return array{0: User, 1: Klasse, 2: Schueler, 3: GradingSystem, 4: GradingQuestion} */
    private function lehrkraftMitKlasse(string ...$extraRechte): array
    {
        [$system, $frage] = $this->systemMitFrage();
        $user = $this->actingAsWithPermission('view paed diary', ...$extraRechte);
        $klasse = Klasse::factory()->create(['grading_system_id' => $system->id]);
        $user->paed_klassen()->attach($klasse->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        return [$user, $klasse, $schueler, $system, $frage];
    }

    private function gruppenSession(User $user, Klasse $klasse, GradingSystem $system, array $attribute = []): GradingDocumentationSession
    {
        return GradingDocumentationSession::factory()->asGroup()->create(array_merge([
            'user_id' => $user->id,
            'klasse_id' => $klasse->id,
            'grading_system_id' => $system->id,
        ], $attribute));
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
    public function system_mit_dokumentationen_oder_klassen_wird_nicht_geloescht(): void
    {
        [$user, $klasse, , $system] = $this->lehrkraftMitKlasse('manage grading systems');
        $this->gruppenSession($user, $klasse, $system);

        $this->post(route('admin.grading.system.delete', $system))->assertSessionHasErrors('system');
        $this->assertDatabaseHas('grading_systems', ['id' => $system->id]);
        $this->assertDatabaseCount('grading_documentation_sessions', 1);

        $leer = GradingSystem::factory()->create();
        $this->post(route('admin.grading.system.delete', $leer))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('grading_systems', ['id' => $leer->id]);
    }

    /** @test */
    public function vergebene_stufe_und_beantwortete_frage_werden_nicht_geloescht(): void
    {
        [$user, $klasse, $schueler, $system, $frage] = $this->lehrkraftMitKlasse('manage grading systems');
        $stufe = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Forscher', 'sort_order' => 1]);
        $schueler->update(['grading_stage_id' => $stufe->id]);
        $alteStufe = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Entdecker', 'sort_order' => 0]);
        SchuelerGradingHistory::create([
            'schueler_id' => $schueler->id, 'grading_system_id' => $system->id,
            'grading_stage_id' => $stufe->id, 'previous_grading_stage_id' => $alteStufe->id, 'created_at' => now(),
        ]);

        $this->post(route('admin.grading.stage.delete', $stufe))->assertSessionHasErrors('stage');
        $this->post(route('admin.grading.stage.delete', $alteStufe))->assertSessionHasErrors('stage');
        $this->assertSame($stufe->id, $schueler->fresh()->grading_stage_id);

        $session = $this->gruppenSession($user, $klasse, $system);
        GradingStudentAnswer::create(['session_id' => $session->id, 'schueler_id' => $schueler->id, 'question_id' => $frage->id, 'self_rating' => 3, 'answered_at' => now()]);
        $this->post(route('admin.grading.question.delete', $frage))->assertSessionHasErrors('question');
        $this->assertDatabaseHas('grading_student_answers', ['question_id' => $frage->id]);
    }

    /** @test */
    public function abgeschlossene_session_ist_im_web_nicht_mehr_beschreibbar(): void
    {
        [$user, $klasse, $schueler, $system, $frage] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system, ['completed_at' => now()]);

        $payload = ['session_id' => $session->id, 'schueler_id' => $schueler->id, 'question_id' => $frage->id];
        $this->postJson(route('gradingDocumentation.saveTeacherAssessment'), $payload + ['teacher_rating' => 4])->assertStatus(409);
        $this->postJson(route('gradingDocumentation.saveStudentAnswer'), $payload + ['self_rating' => 4])->assertStatus(409);
        $this->postJson(route('gradingDocumentation.saveCoachingNote'), ['session_id' => $session->id, 'schueler_id' => $schueler->id, 'note' => 'x'])->assertStatus(409);

        $this->assertDatabaseCount('grading_teacher_assessments', 0);
    }

    /** @test */
    public function fremde_frage_und_fremder_schueler_werden_abgewiesen(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system);
        [, $fremdeFrage] = $this->systemMitFrage();
        $fremderSchueler = Schueler::factory()->create(['klasse_id' => Klasse::factory()->create()->id]);
        [, $eigeneFrage] = [null, GradingQuestion::where('grading_system_id', $system->id)->first()];

        $this->postJson(route('gradingDocumentation.saveTeacherAssessment'), [
            'session_id' => $session->id, 'schueler_id' => $schueler->id, 'question_id' => $fremdeFrage->id, 'teacher_rating' => 3,
        ])->assertStatus(422);
        $this->postJson(route('gradingDocumentation.saveTeacherAssessment'), [
            'session_id' => $session->id, 'schueler_id' => $fremderSchueler->id, 'question_id' => $eigeneFrage->id, 'teacher_rating' => 3,
        ])->assertStatus(422);

        $this->postJson(route('gradingDocumentation.startIndividual'), [
            'klasse_id' => $klasse->id, 'schueler_id' => $fremderSchueler->id,
        ])->assertStatus(422);
        $this->assertDatabaseMissing('grading_documentation_sessions', ['schueler_id' => $fremderSchueler->id]);
    }

    /** @test */
    public function qr_antwort_nach_abschluss_ist_nicht_mehr_moeglich_und_token_verfaellt(): void
    {
        [$user, $klasse, $schueler, $system, $frage] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system);
        $token = $this->token($session, $schueler);
        [, $fremdeFrage] = $this->systemMitFrage();

        $this->postJson(route('gradingDocumentation.savePublicStudentAnswer'), ['token' => $token, 'question_id' => $fremdeFrage->id, 'self_rating' => 5])
            ->assertStatus(422);
        $this->postJson(route('gradingDocumentation.savePublicStudentAnswer'), ['token' => $token, 'question_id' => $frage->id, 'self_rating' => 5])
            ->assertOk();

        $this->postJson(route('gradingDocumentation.completeSession', $session))->assertOk();
        $this->assertDatabaseCount('grading_student_tokens', 0);
        $this->postJson(route('gradingDocumentation.savePublicStudentAnswer'), ['token' => $token, 'question_id' => $frage->id, 'self_rating' => 1])
            ->assertForbidden();

        // Doppelter Abschluss verlängert die Frist nicht
        $completedAt = $session->fresh()->completed_at;
        $this->postJson(route('gradingDocumentation.completeSession', $session))->assertStatus(409);
        $this->assertEquals($completedAt, $session->fresh()->completed_at);
    }

    /** @test */
    public function wiederoeffnen_setzt_abschluesse_einzelner_schueler_zurueck(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system, ['completed_at' => now()]);
        GradingSessionStudent::create(['session_id' => $session->id, 'schueler_id' => $schueler->id, 'finalized_at' => now(), 'finalized_by' => $user->id]);

        $this->postJson(route('gradingDocumentation.reopenSession', $session))->assertOk();

        $this->assertNull($session->fresh()->completed_at);
        $this->assertNull(GradingSessionStudent::where('session_id', $session->id)->value('finalized_at'));
    }

    /** @test */
    public function stufe_vergeben_darf_jede_lehrkraft_mit_zugriff_auf_den_schueler(): void
    {
        [, , $schueler, $system] = $this->lehrkraftMitKlasse();
        $stufe = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Forscher', 'sort_order' => 1]);

        $this->postJson(route('paedDiary.changeStage'), ['schueler_id' => $schueler->id, 'grading_stage_id' => $stufe->id])->assertOk();
        $this->assertSame($stufe->id, $schueler->fresh()->grading_stage_id);

        // Verwaltung der Systeme bleibt "manage grading systems" vorbehalten
        $this->get(route('admin.grading.index'))->assertForbidden();
    }
}
