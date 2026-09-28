<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticSession;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\Schueler;
use Tests\Traits\CreatesTestData;

/**
 * API v1 – Stufenverteilung (Graduierung) und Diagnose-Übersicht einer Klasse.
 */
class ClassOverviewApiTest extends ApiTestCase
{
    use CreatesTestData;

    /** @test */
    public function stufenverteilung_zaehlt_schueler_je_stufe(): void
    {
        $system = GradingSystem::factory()->create();
        $s1 = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Stufe I', 'slug' => 's1', 'sort_order' => 1]);
        $s2 = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Stufe II', 'slug' => 's2', 'sort_order' => 2]);
        $klasse = Klasse::factory()->create(['grading_system_id' => $system->id]);
        Schueler::factory()->count(2)->create(['klasse_id' => $klasse->id, 'grading_stage_id' => $s1->id]);
        $top = Schueler::factory()->create(['klasse_id' => $klasse->id, 'grading_stage_id' => $s2->id, 'vorname' => 'Ada']);
        Schueler::factory()->create(['klasse_id' => $klasse->id, 'grading_stage_id' => null]);
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->getJson(self::API . "/classes/{$klasse->id}/grading/overview")
            ->assertOk()
            ->assertJsonPath('data.students_total', 4)
            ->assertJsonPath('data.grading_system.id', $system->id)
            ->assertJsonPath('data.stages.0.title', 'Stufe I')
            ->assertJsonPath('data.stages.0.count', 2)
            ->assertJsonPath('data.stages.1.count', 1)
            ->assertJsonPath('data.stages.1.students.0.id', $top->id)
            ->assertJsonCount(1, 'data.without_stage');
    }

    /** @test */
    public function stufenverteilung_fremder_klasse_ist_verboten(): void
    {
        $klasse = Klasse::factory()->create();
        $this->actingAsTeacher(['view paed diary'], [Klasse::factory()->create()]);

        $this->getJson(self::API . "/classes/{$klasse->id}/grading/overview")->assertForbidden();
    }

    /** @test */
    public function diagnose_uebersicht_nutzt_die_letzte_bewertung_und_sortiert_nach_foerderbedarf(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $klasse = Klasse::factory()->create();
        [$a, $b, $c] = Schueler::factory()->count(3)->create(['klasse_id' => $klasse->id])->all();
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);

        // Je Schüler und Bereich höchstens eine offene und eine abgeschlossene Sitzung (Unique-Index)
        $rate = function (Schueler $s, string $date, array $ratings, bool $completed = false) use ($area) {
            $session = DiagnosticSession::factory()->state($completed ? ['is_completed' => true, 'completed_at' => $date] : [])->create([
                'schueler_id' => $s->id, 'diagnostic_area_id' => $area->id, 'session_date' => $date,
            ]);
            foreach ($ratings as $goalId => $rating) {
                DiagnosticAssessment::create([
                    'diagnostic_session_id' => $session->id, 'diagnostic_goal_id' => $goalId, 'rating' => $rating,
                ]);
            }
        };

        // Ziel 0: A konnte es früher nicht, jetzt schon → zählt als „kann es“
        $rate($a, '2026-01-10', [$goals[0]->id => 'dark_gray', $goals[1]->id => 'dark_gray'], true);
        $rate($a, '2026-06-10', [$goals[0]->id => 'white']);
        $rate($b, '2026-06-10', [$goals[0]->id => 'gray', $goals[1]->id => 'dark_gray']);
        $rate($c, '2026-06-10', [$goals[1]->id => 'dark_gray', $goals[2]->id => 'white']);

        $response = $this->getJson(self::API . "/classes/{$klasse->id}/diagnostic/overview")->assertOk();
        $response->assertJsonPath('data.students_total', 3)
            ->assertJsonPath('data.assessed_students', 3)
            // Ziel 1: drei Schüler können es noch nicht → ganz oben
            ->assertJsonPath('data.criteria.0.criterion_id', $goals[1]->id)
            ->assertJsonPath('data.criteria.0.counts.dark_gray', 3)
            ->assertJsonCount(3, 'data.criteria.0.students_not_yet')
            ->assertJsonPath('data.criteria.1.criterion_id', $goals[0]->id)
            ->assertJsonPath('data.criteria.1.counts', ['white' => 1, 'gray' => 1, 'dark_gray' => 0, 'assessed' => 2])
            ->assertJsonPath('data.criteria.1.students_partial.0.id', $b->id);
        // Ziel 2: alle können es → nicht in der Liste
        $this->assertCount(2, $response->json('data.criteria'));

        $this->getJson(self::API . "/classes/{$klasse->id}/diagnostic/overview?min_count=2")
            ->assertOk()->assertJsonCount(1, 'data.criteria');
    }

    /** @test */
    public function diagnose_uebersicht_braucht_diagnose_recht(): void
    {
        $klasse = Klasse::factory()->create();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->getJson(self::API . "/classes/{$klasse->id}/diagnostic/overview")->assertForbidden();
    }
}
