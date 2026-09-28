<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticSession;
use App\Models\DiagnosticStage;
use App\Models\Klasse;
use App\Models\Schueler;

class DiagnosticApiTest extends ApiTestCase
{
    private DiagnosticArea $area;
    private DiagnosticStage $stage;
    private DiagnosticGoal $criterion;
    private Klasse $klasse;
    private Schueler $schueler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = DiagnosticArea::factory()->create(['name' => 'Lesekompetenz']);
        $this->stage = DiagnosticStage::factory()->create(['diagnostic_area_id' => $this->area->id]);
        $this->criterion = DiagnosticGoal::factory()->create(['diagnostic_stage_id' => $this->stage->id]);
        [$this->klasse, $this->schueler] = $this->classWithStudent();
    }

    /** @test */
    public function katalog_liefert_bereiche_stufen_und_kriterien(): void
    {
        DiagnosticArea::factory()->inactive()->create();
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);

        $this->getJson(self::API . '/diagnostic/areas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lesekompetenz')
            ->assertJsonPath('data.0.stages.0.id', $this->stage->id)
            ->assertJsonPath('data.0.stages.0.criteria.0.id', $this->criterion->id);
    }

    /** @test */
    public function ohne_diagnoserecht_403(): void
    {
        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);

        $this->getJson(self::API . '/diagnostic/areas')->assertForbidden();
        $this->getJson(self::API . "/students/{$this->schueler->id}/diagnostic/history")->assertForbidden();
    }

    /** @test */
    public function diagnosesitzung_mit_zielen_erfassen(): void
    {
        $user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);

        $response = $this->postJson(self::API . '/diagnostic/sessions', [
            'schueler_id' => $this->schueler->id,
            'area_id' => $this->area->id,
            'stage_id' => $this->stage->id,
            'assessment_notes' => 'Satzverständnis gefestigt.',
            'assessments' => [
                ['criterion_id' => $this->criterion->id, 'rating' => 'gray', 'is_current_goal' => true],
            ],
            'goals' => [
                ['title' => 'Sachtexte mit Nebensätzen sinnentnehmend lesen', 'target_date' => '2026-11-30'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.area_title', 'Lesekompetenz')
            ->assertJsonPath('data.stage_notes.0.notes', 'Satzverständnis gefestigt.')
            ->assertJsonPath('data.assessments.0.rating', 'gray')
            ->assertJsonPath('data.development_goals.0.title', 'Sachtexte mit Nebensätzen sinnentnehmend lesen')
            ->assertJsonPath('data.development_goals.0.target_date', '2026-11-30')
            ->assertJsonPath('data.development_goals.0.status', 'in_progress')
            ->assertJsonPath('meta.resumed_open_session', false);

        $this->assertDatabaseHas('diagnostic_development_goals', [
            'schueler_id' => $this->schueler->id, 'diagnostic_area_id' => $this->area->id, 'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('diagnostic_assessments', ['diagnostic_goal_id' => $this->criterion->id, 'is_current_goal' => true]);

        // Schüler-View zeigt das Ziel und das aktuelle Katalogziel
        $this->getJson(self::API . "/students/{$this->schueler->id}/view")
            ->assertOk()
            ->assertJsonPath('diagnostic_overview.active_goals_count', 1)
            ->assertJsonPath('diagnostic_overview.active_goals.0.area_title', 'Lesekompetenz')
            ->assertJsonPath('diagnostic_overview.current_criterion_goals.0.criterion_id', $this->criterion->id)
            ->assertJsonPath('diagnostic_overview.last_assessment_date', now()->toDateString());
    }

    /** @test */
    public function offene_sitzung_wird_fortgefuehrt(): void
    {
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);
        $open = DiagnosticSession::factory()->create(['schueler_id' => $this->schueler->id, 'diagnostic_area_id' => $this->area->id]);

        $this->postJson(self::API . '/diagnostic/sessions', [
            'schueler_id' => $this->schueler->id,
            'area_id' => $this->area->id,
            'complete' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $open->id)
            ->assertJsonPath('data.is_completed', false)
            ->assertJsonPath('meta.resumed_open_session', true);
    }

    /** @test */
    public function validierung_der_diagnosesitzung(): void
    {
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);
        $fremderBereich = DiagnosticArea::factory()->create();
        $fremdeStufe = DiagnosticStage::factory()->create(['diagnostic_area_id' => $fremderBereich->id]);
        $fremdesKriterium = DiagnosticGoal::factory()->create(['diagnostic_stage_id' => $fremdeStufe->id]);

        $this->postJson(self::API . '/diagnostic/sessions', [])
            ->assertStatus(422)->assertJsonValidationErrors(['schueler_id', 'area_id']);

        $this->postJson(self::API . '/diagnostic/sessions', [
            'schueler_id' => $this->schueler->id,
            'area_id' => $this->area->id,
            'stage_id' => $fremdeStufe->id,
        ])->assertStatus(422)->assertJsonValidationErrors('stage_id');

        $this->postJson(self::API . '/diagnostic/sessions', [
            'schueler_id' => $this->schueler->id,
            'area_id' => $this->area->id,
            'assessments' => [['criterion_id' => $fremdesKriterium->id, 'rating' => 'white']],
        ])->assertStatus(422)->assertJsonValidationErrors('assessments');

        $this->postJson(self::API . '/diagnostic/sessions', [
            'schueler_id' => $this->schueler->id,
            'area_id' => $this->area->id,
            'goals' => [['title' => '', 'target_date' => '30.11.2026']],
        ])->assertStatus(422)->assertJsonValidationErrors(['goals.0.title', 'goals.0.target_date']);

        $this->assertDatabaseCount('diagnostic_sessions', 0);
    }

    /** @test */
    public function sitzung_fuer_fremden_schueler_liefert_403(): void
    {
        [, $fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);

        $this->postJson(self::API . '/diagnostic/sessions', [
            'schueler_id' => $fremd->id,
            'area_id' => $this->area->id,
        ])->assertForbidden();
    }

    /** @test */
    public function zielstatus_aktualisieren(): void
    {
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);
        $goal = DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $this->schueler->id]);

        $this->putJson(self::API . "/diagnostic/goals/{$goal->id}", [
            'status' => 'achieved',
            'completion_notes' => 'Ziel vorzeitig in der Freiarbeit erreicht.',
            'completed_at' => '2026-09-23',
        ])->assertOk()
            ->assertJsonPath('data.status', 'achieved')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.completed_at', '2026-09-23')
            ->assertJsonPath('data.completion_notes', 'Ziel vorzeitig in der Freiarbeit erreicht.');

        // Zurück auf "in Arbeit" entfernt das Abschlussdatum
        $this->putJson(self::API . "/diagnostic/goals/{$goal->id}", ['status' => 'in_progress', 'target_date' => '2026-12-15'])
            ->assertOk()
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.target_date', '2026-12-15');

        $this->putJson(self::API . "/diagnostic/goals/{$goal->id}", ['status' => 'erledigt'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    /** @test */
    public function ziel_archivieren_und_endgueltig_loeschen(): void
    {
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);
        $goal = DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $this->schueler->id]);

        $this->deleteJson(self::API . "/diagnostic/goals/{$goal->id}")
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->assertNotNull($goal->fresh()->archived_at);

        // Endgültig löschen nur mit "manage diagnostics"
        $this->deleteJson(self::API . "/diagnostic/goals/{$goal->id}?force=true")->assertForbidden();

        $this->actingAsTeacher(['view paed diary', 'view diagnostics', 'manage diagnostics'], [$this->klasse]);
        $this->deleteJson(self::API . "/diagnostic/goals/{$goal->id}?force=true")->assertNoContent();
        $this->assertDatabaseMissing('diagnostic_development_goals', ['id' => $goal->id]);
    }

    /** @test */
    public function historie_liefert_sitzungen_und_ziele(): void
    {
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);
        $session = DiagnosticSession::factory()->completed()->create(['schueler_id' => $this->schueler->id, 'diagnostic_area_id' => $this->area->id]);
        DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $this->schueler->id, 'diagnostic_session_id' => $session->id]);
        DiagnosticDevelopmentGoal::factory()->archived()->create(['schueler_id' => $this->schueler->id]);

        $this->getJson(self::API . "/students/{$this->schueler->id}/diagnostic/history")
            ->assertOk()
            ->assertJsonPath('data.sessions.0.id', $session->id)
            ->assertJsonCount(1, 'data.sessions.0.development_goals')
            ->assertJsonCount(1, 'data.development_goals');

        $this->getJson(self::API . "/students/{$this->schueler->id}/diagnostic/history?include_archived_goals=true")
            ->assertOk()->assertJsonCount(2, 'data.development_goals');
    }
}
