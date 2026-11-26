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
