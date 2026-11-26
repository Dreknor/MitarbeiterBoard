<?php

namespace Tests\Unit\Models\Diagnostic;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticSession;
use App\Models\DiagnosticStage;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class DiagnosticAssessmentTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_assessment_hat_session_relation(): void
    {
        $session    = DiagnosticSession::factory()->create();
        $goal       = DiagnosticGoal::factory()->create();
        $assessment = DiagnosticAssessment::factory()->create([
            'diagnostic_session_id' => $session->id,
            'diagnostic_goal_id'    => $goal->id,
        ]);

        $this->assertEquals($session->id, $assessment->session->id);
    }

    public function test_assessment_hat_goal_relation(): void
    {
        $goal       = DiagnosticGoal::factory()->create();
        $assessment = DiagnosticAssessment::factory()->create([
            'diagnostic_goal_id' => $goal->id,
        ]);

        $this->assertEquals($goal->id, $assessment->goal->id);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_assessment_is_current_goal_ist_boolean(): void
    {
        $assessment = DiagnosticAssessment::factory()->create(['is_current_goal' => false]);
        $this->assertIsBool($assessment->is_current_goal);
    }

    public function test_assessment_saved_at_ist_datetime(): void
    {
        $assessment = DiagnosticAssessment::factory()->create();
        $this->assertInstanceOf(Carbon::class, $assessment->saved_at);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function test_scopeCurrentGoals_gibt_aktuelle_ziele(): void
    {
        DiagnosticAssessment::factory()->create(['is_current_goal' => true]);
        DiagnosticAssessment::factory()->create(['is_current_goal' => false]);

        $result = DiagnosticAssessment::currentGoals()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopeWithRating_filtert_nach_rating(): void
    {
        DiagnosticAssessment::factory()->create(['rating' => 'dark_gray']);
        DiagnosticAssessment::factory()->create(['rating' => 'gray']);

        $result = DiagnosticAssessment::withRating('dark_gray')->get();
        $this->assertCount(1, $result);
    }

    // ─── Rating-Werte ────────────────────────────────────────────────────────

    public function test_assessment_isCurrentGoal_methode(): void
    {
        $current = DiagnosticAssessment::factory()->asCurrentGoal()->create();
        $other   = DiagnosticAssessment::factory()->create(['is_current_goal' => false]);

        $this->assertTrue($current->isCurrentGoal());
        $this->assertFalse($other->isCurrentGoal());
    }
}
