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
