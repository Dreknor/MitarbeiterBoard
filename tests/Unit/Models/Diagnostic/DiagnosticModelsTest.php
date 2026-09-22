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

class DiagnosticAreaTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_area_hat_stages_relation(): void
    {
        $area  = DiagnosticArea::factory()->create();
        DiagnosticStage::factory()->for($area, 'area')->create(['sort_order' => 1]);
        DiagnosticStage::factory()->for($area, 'area')->create(['sort_order' => 2]);

        $this->assertCount(2, $area->stages);
    }

    public function test_stages_sind_nach_sort_order_sortiert(): void
    {
        $area  = DiagnosticArea::factory()->create();
        DiagnosticStage::factory()->for($area, 'area')->create(['sort_order' => 3, 'name' => 'Dritte']);
        DiagnosticStage::factory()->for($area, 'area')->create(['sort_order' => 1, 'name' => 'Erste']);
        DiagnosticStage::factory()->for($area, 'area')->create(['sort_order' => 2, 'name' => 'Zweite']);

        $stages = $area->stages()->get();
        $this->assertEquals('Erste', $stages->first()->name);
        $this->assertEquals('Dritte', $stages->last()->name);
    }

    public function test_area_hat_sessions_relation(): void
    {
        $area    = DiagnosticArea::factory()->create();
        $schueler = Schueler::factory()->create();
        $user    = User::factory()->create();

        DiagnosticSession::factory()->create([
            'diagnostic_area_id' => $area->id,
            'schueler_id'        => $schueler->id,
            'user_id'            => $user->id,
        ]);

        $this->assertCount(1, $area->sessions);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function test_scopeActive_gibt_nur_aktive_bereiche(): void
    {
        DiagnosticArea::factory()->create(['active' => true]);
        DiagnosticArea::factory()->inactive()->create();

        $result = DiagnosticArea::active()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopeOrdered_sortiert_nach_sort_order(): void
    {
        DiagnosticArea::factory()->create(['sort_order' => 3, 'name' => 'Z-Bereich']);
        DiagnosticArea::factory()->create(['sort_order' => 1, 'name' => 'A-Bereich']);

        $result = DiagnosticArea::ordered()->get();
        $this->assertEquals('A-Bereich', $result->first()->name);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_area_active_ist_boolean(): void
    {
        $area = DiagnosticArea::factory()->create(['active' => true]);
        $this->assertIsBool($area->active);
    }
}

class DiagnosticSessionTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_session_hat_schueler_relation(): void
    {
        $schueler = Schueler::factory()->create();
        $session  = DiagnosticSession::factory()->create(['schueler_id' => $schueler->id]);

        $this->assertEquals($schueler->id, $session->schueler->id);
    }

    public function test_session_hat_area_relation(): void
    {
        $area    = DiagnosticArea::factory()->create();
        $session = DiagnosticSession::factory()->create(['diagnostic_area_id' => $area->id]);

        $this->assertEquals($area->id, $session->area->id);
    }

    public function test_session_hat_user_relation(): void
    {
        $user    = User::factory()->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $session->user->id);
    }

    public function test_session_hat_assessments_relation(): void
    {
        $session    = DiagnosticSession::factory()->create();
        $goal       = DiagnosticGoal::factory()->create();
        DiagnosticAssessment::factory()->create([
            'diagnostic_session_id' => $session->id,
            'diagnostic_goal_id'    => $goal->id,
        ]);

        $this->assertCount(1, $session->assessments);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_session_is_completed_ist_boolean(): void
    {
        $session = DiagnosticSession::factory()->create(['is_completed' => false]);
        $this->assertIsBool($session->is_completed);
        $this->assertFalse($session->is_completed);
    }

    public function test_session_session_date_ist_date(): void
    {
        $session = DiagnosticSession::factory()->create(['session_date' => '2026-03-09']);
        $this->assertInstanceOf(Carbon::class, $session->session_date);
    }

    public function test_session_completed_at_ist_datetime(): void
    {
        $session = DiagnosticSession::factory()->completed()->create();
        $this->assertInstanceOf(Carbon::class, $session->completed_at);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function test_scopeOpen_gibt_offene_sessions(): void
    {
        DiagnosticSession::factory()->create(['is_completed' => false]);
        DiagnosticSession::factory()->completed()->create();

        $result = DiagnosticSession::open()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopeCompleted_gibt_abgeschlossene_sessions(): void
    {
        DiagnosticSession::factory()->create(['is_completed' => false]);
        DiagnosticSession::factory()->completed()->create();

        $result = DiagnosticSession::completed()->get();
        $this->assertCount(1, $result);
    }

    public function test_scopeForSchueler_filtert_nach_schueler(): void
    {
        $s1 = Schueler::factory()->create();
        $s2 = Schueler::factory()->create();

        DiagnosticSession::factory()->create(['schueler_id' => $s1->id]);
        DiagnosticSession::factory()->create(['schueler_id' => $s2->id]);

        $result = DiagnosticSession::forSchueler($s1->id)->get();
        $this->assertCount(1, $result);
        $this->assertEquals($s1->id, $result->first()->schueler_id);
    }

    // ─── complete / reopen ───────────────────────────────────────────────────

    public function test_session_kann_abgeschlossen_werden(): void
    {
        $session = DiagnosticSession::factory()->create(['is_completed' => false]);
        $session->complete();
        $session->refresh();

        $this->assertTrue($session->is_completed);
        $this->assertNotNull($session->completed_at);
    }

    public function test_session_kann_wieder_geoeffnet_werden(): void
    {
        $session = DiagnosticSession::factory()->completed()->create();
        $session->reopen();
        $session->refresh();

        $this->assertFalse($session->is_completed);
        $this->assertNull($session->completed_at);
    }

    // ─── isCompleted ─────────────────────────────────────────────────────────

    public function test_isCompleted_gibt_true_fuer_abgeschlossene_session(): void
    {
        $session = DiagnosticSession::factory()->completed()->create();
        $this->assertTrue($session->isCompleted());
    }

    public function test_isCompleted_gibt_false_fuer_offene_session(): void
    {
        $session = DiagnosticSession::factory()->create(['is_completed' => false]);
        $this->assertFalse($session->isCompleted());
    }
}

class DiagnosticGoalTest extends TestCase
{
    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_goal_hat_stage_relation(): void
    {
        $area  = DiagnosticArea::factory()->create();
        $stage = DiagnosticStage::factory()->for($area, 'area')->create();
        $goal  = DiagnosticGoal::factory()->create(['diagnostic_stage_id' => $stage->id]);

        $this->assertEquals($stage->id, $goal->stage->id);
    }

    public function test_goal_hat_assessments_relation(): void
    {
        $goal    = DiagnosticGoal::factory()->create();
        $session = DiagnosticSession::factory()->create();
        DiagnosticAssessment::factory()->create([
            'diagnostic_goal_id'    => $goal->id,
            'diagnostic_session_id' => $session->id,
        ]);

        $this->assertCount(1, $goal->assessments);
    }

    // ─── scopeOrdered ────────────────────────────────────────────────────────

    public function test_scopeOrdered_sortiert_goals(): void
    {
        $stage = DiagnosticStage::factory()->create();
        DiagnosticGoal::factory()->create(['diagnostic_stage_id' => $stage->id, 'sort_order' => 2, 'code' => 'B']);
        DiagnosticGoal::factory()->create(['diagnostic_stage_id' => $stage->id, 'sort_order' => 1, 'code' => 'A']);

        $goals = DiagnosticGoal::where('diagnostic_stage_id', $stage->id)->ordered()->get();
        $this->assertEquals('A', $goals->first()->code);
    }
}

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

