<?php

namespace Tests\Feature;

use App\Models\GradingDocumentationSession;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Tests\TestCase;

class GradingDocumentationAnswerOrderModeTest extends TestCase
{
    public function test_group_session_stores_selected_answer_order_mode_when_created(): void
    {
        [$user, $klasse] = $this->createTeacherWithAssignedClass();

        $response = $this->postJson(route('gradingDocumentation.startGroup'), [
            'klasse_id' => $klasse->id,
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'resumed' => false,
            ]);

        $this->assertDatabaseHas('grading_documentation_sessions', [
            'user_id' => $user->id,
            'klasse_id' => $klasse->id,
            'type' => 'group',
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);
    }

    public function test_resuming_group_session_updates_answer_order_mode_from_start_selection(): void
    {
        [$user, $klasse, $gradingSystem] = $this->createTeacherWithAssignedClass();

        $session = GradingDocumentationSession::factory()->asGroup()->create([
            'klasse_id' => $klasse->id,
            'grading_system_id' => $gradingSystem->id,
            'user_id' => $user->id,
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
        ]);

        $response = $this->postJson(route('gradingDocumentation.startGroup'), [
            'klasse_id' => $klasse->id,
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'resumed' => true,
                'session' => ['id' => $session->id],
            ]);

        $this->assertSame(
            GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
            $session->fresh()->answer_order_mode
        );
    }

    public function test_individual_session_uses_student_order_by_default(): void
    {
        [$user, $klasse] = $this->createTeacherWithAssignedClass();
        $schueler = Schueler::factory()->create([
            'klasse_id' => $klasse->id,
        ]);

        $response = $this->postJson(route('gradingDocumentation.startIndividual'), [
            'klasse_id' => $klasse->id,
            'schueler_id' => $schueler->id,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'resumed' => false,
            ]);

        $this->assertDatabaseHas('grading_documentation_sessions', [
            'user_id' => $user->id,
            'klasse_id' => $klasse->id,
            'schueler_id' => $schueler->id,
            'type' => 'individual',
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
        ]);
    }

    public function test_open_group_session_answer_order_mode_can_be_updated(): void
    {
        [$user, $klasse, $gradingSystem] = $this->createTeacherWithAssignedClass();

        $session = GradingDocumentationSession::factory()->asGroup()->create([
            'klasse_id' => $klasse->id,
            'grading_system_id' => $gradingSystem->id,
            'user_id' => $user->id,
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
        ]);

        $response = $this->postJson(route('gradingDocumentation.updateAnswerOrderMode', $session), [
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
                'answer_order_mode_label' => 'Fragenweise',
            ]);

        $this->assertDatabaseHas('grading_documentation_sessions', [
            'id' => $session->id,
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);
    }

    public function test_completed_session_answer_order_mode_cannot_be_updated(): void
    {
        [$user, $klasse, $gradingSystem] = $this->createTeacherWithAssignedClass();

        $session = GradingDocumentationSession::factory()->asGroup()->completed()->create([
            'klasse_id' => $klasse->id,
            'grading_system_id' => $gradingSystem->id,
            'user_id' => $user->id,
        ]);

        $response = $this->postJson(route('gradingDocumentation.updateAnswerOrderMode', $session), [
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);

        $response->assertStatus(422);

        $this->assertSame(
            GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
            $session->fresh()->answer_order_mode
        );
    }

    public function test_individual_session_cannot_be_switched_to_question_order(): void
    {
        [$user, $klasse, $gradingSystem] = $this->createTeacherWithAssignedClass();
        $schueler = Schueler::factory()->create([
            'klasse_id' => $klasse->id,
        ]);

        $session = GradingDocumentationSession::factory()->create([
            'klasse_id' => $klasse->id,
            'grading_system_id' => $gradingSystem->id,
            'user_id' => $user->id,
            'schueler_id' => $schueler->id,
            'type' => 'individual',
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
        ]);

        $response = $this->postJson(route('gradingDocumentation.updateAnswerOrderMode', $session), [
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);

        $response->assertStatus(422);

        $this->assertSame(
            GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
            $session->fresh()->answer_order_mode
        );
    }

    private function createTeacherWithAssignedClass(): array
    {
        /** @var User $user */
        $user = $this->actingAsWithPermission('view paed diary');
        $gradingSystem = GradingSystem::factory()->create();
        $klasse = Klasse::factory()->create([
            'grading_system_id' => $gradingSystem->id,
        ]);

        $user->paed_klassen()->attach($klasse->id);

        return [$user, $klasse, $gradingSystem];
    }
}

