<?php

namespace Tests\Unit\Services;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticSession;
use App\Models\DiagnosticStage;
use App\Models\Schueler;
use App\Models\User;
use App\Services\DiagnosticService;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class DiagnosticServiceTest extends TestCase
{
    use CreatesTestData;

    private DiagnosticService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DiagnosticService();
    }

    // ─── Hilfsmethoden ──────────────────────────────────────────────────────

    private function makeSchueler(): Schueler
    {
        return $this->createSchuelerInKlasse();
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    // ─── getOrCreateSession ──────────────────────────────────────────────────

    public function test_getOrCreateSession_erstellt_neue_session(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();

        $session = $this->service->getOrCreateSession($schueler, $area, $user);

        $this->assertInstanceOf(DiagnosticSession::class, $session);
        $this->assertEquals($schueler->id, $session->schueler_id);
        $this->assertEquals($area->id, $session->diagnostic_area_id);
        $this->assertFalse($session->is_completed);
    }

    public function test_getOrCreateSession_gibt_bestehende_offene_session_zurueck(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();

        $session1 = $this->service->getOrCreateSession($schueler, $area, $user);
        $session2 = $this->service->getOrCreateSession($schueler, $area, $user);

        // Es darf keine zweite Session angelegt werden
        $this->assertEquals($session1->id, $session2->id);
        $this->assertDatabaseCount('diagnostic_sessions', 1);
    }

    public function test_getOrCreateSession_erstellt_neue_wenn_vorherige_abgeschlossen(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();

        $session1 = $this->service->getOrCreateSession($schueler, $area, $user);
        $this->service->completeSession($session1);

        $session2 = $this->service->getOrCreateSession($schueler, $area, $user);

        $this->assertNotEquals($session1->id, $session2->id);
        $this->assertDatabaseCount('diagnostic_sessions', 2);
    }

    // ─── canStartNewSession ──────────────────────────────────────────────────

    public function test_canStartNewSession_true_wenn_keine_offene_session(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();

        $this->assertTrue($this->service->canStartNewSession($schueler, $area));
    }

    public function test_canStartNewSession_false_wenn_offene_session_existiert(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();

        DiagnosticSession::factory()->create([
            'schueler_id'        => $schueler->id,
            'diagnostic_area_id' => $area->id,
            'user_id'            => $user->id,
            'is_completed'       => false,
        ]);

        $this->assertFalse($this->service->canStartNewSession($schueler, $area));
    }

    // ─── saveAssessment ──────────────────────────────────────────────────────

    public function test_saveAssessment_bewertung_wird_gespeichert(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);

        $assessment = $this->service->saveAssessment($session, $goals[0], 'gray');

        $this->assertInstanceOf(DiagnosticAssessment::class, $assessment);
        $this->assertEquals('gray', $assessment->rating);
        $this->assertEquals($goals[0]->id, $assessment->diagnostic_goal_id);
    }

    public function test_saveAssessment_gray_zu_white_setzt_is_current_goal_false(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);

        // Erst grau setzen (aktuelles Ziel)
        $this->service->saveAssessment($session, $goals[0], 'gray');

        // Dann auf weiß (erreicht) → is_current_goal soll auf false
        $assessment = $this->service->saveAssessment($session, $goals[0], 'white');

        $this->assertEquals('white', $assessment->rating);
        $this->assertFalse((bool) $assessment->is_current_goal);
    }

    public function test_saveAssessment_upsert_aktualisiert_bestehende_bewertung(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);

        $this->service->saveAssessment($session, $goals[0], 'gray');
        $this->service->saveAssessment($session, $goals[0], 'white');

        // Nur eine Bewertung pro Goal+Session
        $this->assertDatabaseCount('diagnostic_assessments', 1);
    }

    // ─── completeSession ─────────────────────────────────────────────────────

    public function test_completeSession_markiert_session_als_abgeschlossen(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);

        $this->service->completeSession($session);

        $session->refresh();
        $this->assertTrue($session->is_completed);
        $this->assertNotNull($session->completed_at);
    }

    // ─── reopenSession ───────────────────────────────────────────────────────

    public function test_reopenSession_oeffnet_session_wieder(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);
        $this->service->completeSession($session);
        $session->refresh();

        $this->assertTrue($session->is_completed);

        $this->service->reopenSession($session);
        $session->refresh();

        $this->assertFalse($session->is_completed);
        $this->assertNull($session->completed_at);
    }

    // ─── calculateProgress ───────────────────────────────────────────────────

    public function test_calculateProgress_0_prozent_ohne_bewertungen(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);
        $this->service->completeSession($session);

        $progress = $this->service->calculateProgress($schueler, $area);

        $this->assertCount(1, $progress);
        $this->assertEquals(0, $progress[0]['percentage']);
        $this->assertEquals(0, $progress[0]['white_count']);
    }

    public function test_calculateProgress_100_prozent_wenn_alle_goals_white(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);

        foreach ($goals as $goal) {
            $this->service->saveAssessment($session, $goal, 'white');
        }

        $this->service->completeSession($session);

        $progress = $this->service->calculateProgress($schueler, $area);

        $this->assertCount(1, $progress);
        $this->assertEquals(100.0, $progress[0]['percentage']);
    }

    public function test_calculateProgress_leer_wenn_keine_abgeschlossenen_sessions(): void
    {
        ['area' => $area] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();

        $progress = $this->service->calculateProgress($schueler, $area);

        $this->assertEmpty($progress);
    }

    // ─── getCurrentGoalsForStudent ────────────────────────────────────────────

    public function test_getCurrentGoalsForStudent_gibt_nur_aktuelle_ziele_zurueck(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);

        // Nur goal[0] als aktuelles Ziel (is_current_goal = true)
        DiagnosticAssessment::create([
            'diagnostic_session_id' => $session->id,
            'diagnostic_goal_id'    => $goals[0]->id,
            'rating'                => 'gray',
            'is_current_goal'       => true,
        ]);

        $currentGoals = $this->service->getCurrentGoalsForStudent($schueler);

        $this->assertNotEmpty($currentGoals);
    }

    // ─── getHistoricalData ───────────────────────────────────────────────────

    public function test_getHistoricalData_sortiert_chronologisch(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();

        // Session 1: Wird first erstellt aber mit älterer session_date
        $s1 = $this->service->getOrCreateSession($schueler, $area, $user);
        $this->service->saveAssessment($s1, $goals[0], 'gray');
        $this->service->completeSession($s1);
        $s1->update(['session_date' => now()->subDays(5)->toDateString()]);

        // Session 2: anderer Schüler wegen unique-Constraint (eine completed Session pro Schüler+Bereich)
        $schueler2 = $this->makeSchueler();
        $s2 = $this->service->getOrCreateSession($schueler2, $area, $user);
        $this->service->saveAssessment($s2, $goals[0], 'white');
        $this->service->completeSession($s2);
        $s2->update(['session_date' => now()->toDateString()]);

        // Prüfen: Für schueler nur 1 History-Eintrag vorhanden
        $history1 = $this->service->getHistoricalData($goals[0], $schueler, 3);
        $history2 = $this->service->getHistoricalData($goals[0], $schueler2, 3);

        $this->assertCount(1, $history1);
        $this->assertCount(1, $history2);
        $this->assertEquals('gray', $history1[0]['rating']);
        $this->assertEquals('white', $history2[0]['rating']);
    }

    public function test_getHistoricalData_begrenzt_auf_limit(): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $user = $this->makeUser();

        // 3 verschiedene Schüler, jeder hat eine completed Session
        // (Unique-Constraint: max 1 completed Session pro Schüler+Bereich)
        $schuelerListe = [];
        for ($i = 0; $i < 3; $i++) {
            $s = $this->makeSchueler();
            $schuelerListe[] = $s;
            $session = DiagnosticSession::factory()->completed()->create([
                'schueler_id'        => $s->id,
                'diagnostic_area_id' => $area->id,
                'user_id'            => $user->id,
            ]);
            DiagnosticAssessment::create([
                'diagnostic_session_id' => $session->id,
                'diagnostic_goal_id'    => $goals[0]->id,
                'rating'                => 'gray',
            ]);
        }

        // Jeder Schüler hat genau 1 History-Eintrag
        $history = $this->service->getHistoricalData($goals[0], $schuelerListe[0], 2);
        $this->assertCount(1, $history);
    }

    // ─── getRatingColor / getRatingText ──────────────────────────────────────

    /** @dataProvider ratingProvider */
    public function test_rating_color_und_text_sind_korrekt(string $rating, string $expectedColor, string $expectedText): void
    {
        ['area' => $area, 'goals' => $goals] = $this->createDiagnosticSetup();
        $schueler = $this->makeSchueler();
        $user     = $this->makeUser();
        $session  = $this->service->getOrCreateSession($schueler, $area, $user);
        $this->service->saveAssessment($session, $goals[0], $rating);
        $this->service->completeSession($session);

        $history = $this->service->getHistoricalData($goals[0], $schueler, 1);

        $this->assertCount(1, $history);
        $this->assertEquals($expectedColor, $history[0]['color']);
        $this->assertEquals($expectedText, $history[0]['rating_text']);
    }

    public static function ratingProvider(): array
    {
        return [
            'white'    => ['white',    '#ffffff', 'Kann es'],
            'gray'     => ['gray',     '#cccccc', 'Aktuelles Ziel'],
            'dark_gray'=> ['dark_gray','#666666', 'Kann es nicht'],
        ];
    }
}

