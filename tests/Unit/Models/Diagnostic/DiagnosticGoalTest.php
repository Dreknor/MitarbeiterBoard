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
