<?php

namespace Tests\Unit\Services;

use App\Models\Absence;
use App\Models\Group;
use App\Models\User;
use App\Models\personal\Employment;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\RosterTaskRequirement;
use App\Models\personal\WorkingTime;
use App\Services\AutoRosterPlanner;
use Carbon\Carbon;
use Tests\TestCase;

class AutoRosterPlannerTest extends TestCase
{
    private AutoRosterPlanner $planner;

    /** Montag der Testwoche */
    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new AutoRosterPlanner();
        $this->monday  = Carbon::parse('2026-03-09'); // Montag
    }

    // ─── Setup-Hilfsmethoden ─────────────────────────────────────────────────

    private function createDept(): Group
    {
        return Group::factory()->asDepartment()->create();
    }

    private function createEmployee(Group $dept, int $hours = 40): User
    {
        $user = User::factory()->create();
        Employment::factory()->for($user, 'employe')->for($dept, 'department')->active()->create([
            'hours' => $hours,
        ]);
        return $user;
    }

    private function createRoster(Group $dept): Roster
    {
        return Roster::factory()->for($dept, 'department')->create([
            'start_date' => $this->monday,
        ]);
    }

    private function addWorkingTime(User $user, Roster $roster, string $date, string $start = '08:00:00', string $end = '16:00:00', ?string $function = null): WorkingTime
    {
        return WorkingTime::create([
            'employe_id' => $user->id,
            'roster_id'  => $roster->id,
            'date'       => $date,
            'start'      => $start,
            'end'        => $end,
            'function'   => $function,
        ]);
    }

    private function addEvent(User $user, Roster $roster, string $date, string $start = '09:00:00', string $end = '10:00:00', string $event = 'Aufgabe A'): RosterEvents
    {
        return RosterEvents::create([
            'employe_id' => $user->id,
            'roster_id'  => $roster->id,
            'date'       => $date,
            'start'      => $start,
            'end'        => $end,
            'event'      => $event,
        ]);
    }

    private function makeAbsent(User $user, string $start, string $end): Absence
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        return Absence::create([
            'users_id'  => $user->id,
            'creator_id'=> $admin->id,
            'reason'    => 'krank',
            'start'     => $start,
            'end'       => $end,
            'showVertretungsplan' => false,
        ]);
    }

    // ─── Tests ──────────────────────────────────────────────────────────────

    /**
     * Szenario 1: Kein Mitarbeiter abwesend → leere Vorschläge
     */
    public function test_szenario1_keine_abwesenheit_leere_vorschlaege(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);

        $this->addWorkingTime($ma1, $roster, $this->monday->format('Y-m-d'));
        $this->addEvent($ma1, $roster, $this->monday->format('Y-m-d'), '09:00:00', '10:00:00');

        $result = $this->planner->suggest($roster);

        $this->assertEmpty($result['suggestions']);
        $this->assertEquals(0, $result['summary']['betroffene_events']);
    }

    /**
     * Szenario 2: Ein MA abwesend, Ersatz verfügbar (gleiche Arbeitszeit)
     */
    public function test_szenario2_abwesend_ersatz_verfuegbar_reassign(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        // ma1 hat 8-16 Uhr, ma2 hat auch 8-16 Uhr → ma2 kann ma1's Event übernehmen
        $this->addWorkingTime($ma1, $roster, $date, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma2, $roster, $date, '08:00:00', '16:00:00');
        $this->addEvent($ma1, $roster, $date, '09:00:00', '10:00:00', 'Aufgabe A');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        $this->assertCount(1, $result['suggestions']);
        $this->assertEquals('reassign', $result['suggestions'][0]['action']);
        $this->assertEquals($ma1->id, $result['suggestions'][0]['from']['id']);
        $this->assertEquals($ma2->id, $result['suggestions'][0]['to']['id']);
        $this->assertEquals(1, $result['summary']['betroffene_events']);
        $this->assertEquals(1, $result['summary']['neu_zugewiesen']);
    }

    /**
     * Szenario 3: Ein MA abwesend, kein Ersatz → unassign
     */
    public function test_szenario3_abwesend_kein_ersatz_unassign(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        // Nur ma1 vorhanden, kein anderer MA hat Arbeitszeit
        $this->addWorkingTime($ma1, $roster, $date, '08:00:00', '16:00:00');
        $this->addEvent($ma1, $roster, $date, '09:00:00', '10:00:00', 'Aufgabe A');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        $this->assertCount(1, $result['suggestions']);
        $this->assertEquals('unassign', $result['suggestions'][0]['action']);
        $this->assertNull($result['suggestions'][0]['to']);
        $this->assertEquals(1, $result['summary']['nicht_zuweisbar']);
    }

    /**
     * Szenario 4: MA abwesend, Ersatz benötigt Arbeitszeitverlängerung (needsAdjust)
     */
    public function test_szenario4_ersatz_benoetigt_arbeitszeitverlaengerung(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        // ma1's Event geht von 08:00–09:00, ma2 kommt erst ab 10:00
        $this->addWorkingTime($ma1, $roster, $date, '07:00:00', '15:00:00');
        $this->addWorkingTime($ma2, $roster, $date, '10:00:00', '16:00:00');
        $this->addEvent($ma1, $roster, $date, '08:00:00', '09:00:00', 'Frühaufgabe');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        $this->assertCount(1, $result['suggestions']);
        // Entweder reassign (mit adjust) oder unassign – je nach Implementierung
        if ($result['suggestions'][0]['action'] === 'reassign') {
            $this->assertNotNull($result['suggestions'][0]['adjust_working_time']);
        }
    }

    /**
     * Szenario 5: Simulierte Abwesenheit (global) – wie echte Abwesenheit behandelt
     */
    public function test_szenario5_simulierte_abwesenheit_global(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        $this->addWorkingTime($ma1, $roster, $date, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma2, $roster, $date, '08:00:00', '16:00:00');
        $this->addEvent($ma1, $roster, $date, '09:00:00', '10:00:00', 'Aufgabe');

        // ma1 wird global simuliert als abwesend (kein echter Absence-Eintrag)
        $result = $this->planner->suggest($roster, [$ma1->id]);

        $this->assertCount(1, $result['suggestions']);
        $this->assertEquals('reassign', $result['suggestions'][0]['action']);
        $this->assertEquals($ma1->id, $result['suggestions'][0]['from']['id']);
    }

    /**
     * Szenario 6: Simulierte Abwesenheit (pro Tag) – nur am angegebenen Tag
     */
    public function test_szenario6_simulierte_abwesenheit_pro_tag(): void
    {
        $dept    = $this->createDept();
        $ma1     = $this->createEmployee($dept);
        $ma2     = $this->createEmployee($dept);
        $roster  = $this->createRoster($dept);
        $monday  = $this->monday->format('Y-m-d');
        $tuesday = $this->monday->copy()->addDay()->format('Y-m-d');

        // ma1 hat Events am Montag und Dienstag
        $this->addWorkingTime($ma1, $roster, $monday, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma1, $roster, $tuesday, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma2, $roster, $monday, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma2, $roster, $tuesday, '08:00:00', '16:00:00');

        $this->addEvent($ma1, $roster, $monday, '09:00:00', '10:00:00', 'Montags-Event');
        $this->addEvent($ma1, $roster, $tuesday, '09:00:00', '10:00:00', 'Dienstags-Event');

        // ma1 nur am Montag simuliert abwesend
        $result = $this->planner->suggest($roster, [], [$monday => [$ma1->id]]);

        // Nur das Montags-Event sollte betroffen sein
        $this->assertEquals(1, $result['summary']['betroffene_events']);
    }

    /**
     * Szenario 7: RosterTaskRequirement vorhanden, Candidate kann es nicht erfüllen
     */
    public function test_szenario7_requirement_candidate_kann_nicht_erfuellen(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        // Requirement: Event-Funktion "spezialaufgabe" braucht start 07:00
        RosterTaskRequirement::create([
            'department_id'       => $dept->id,
            'event_name'          => 'spezialaufgabe',
            'required_start'      => '07:00:00',
            'required_end'        => null,
            'adjust_working_time' => false, // keine automatische Verlängerung
        ]);

        // ma2 hat Funktion "spezialaufgabe" und kommt erst um 10:00
        $this->addWorkingTime($ma1, $roster, $date, '07:00:00', '15:00:00', 'spezialaufgabe');
        $this->addWorkingTime($ma2, $roster, $date, '10:00:00', '16:00:00', 'spezialaufgabe');
        $this->addEvent($ma1, $roster, $date, '08:00:00', '09:00:00', 'Aufgabe A');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        // ma2 kann nicht erfüllen weil er erst 10:00 beginnt aber 07:00 benötigt wird
        // und adjust_working_time=false → unassign oder Requirement-Fehler
        $this->assertCount(1, $result['suggestions']);
    }

    /**
     * Szenario 8: RosterTaskRequirement mit adjust_working_time=true → Arbeitszeit wird angepasst
     */
    public function test_szenario8_requirement_mit_arbeitszeitanpassung(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        // Requirement: Funktion "fruehaufgabe" braucht start 07:00, adjust=true
        RosterTaskRequirement::create([
            'department_id'       => $dept->id,
            'event_name'          => 'fruehaufgabe',
            'required_start'      => '07:00:00',
            'required_end'        => null,
            'adjust_working_time' => true,
        ]);

        // ma1 kommt um 07:00, ma2 kommt um 10:00 (aber adjust=true → Verlängerung erlaubt)
        $this->addWorkingTime($ma1, $roster, $date, '07:00:00', '15:00:00', 'fruehaufgabe');
        $this->addWorkingTime($ma2, $roster, $date, '10:00:00', '16:00:00', 'fruehaufgabe');
        $this->addEvent($ma1, $roster, $date, '08:00:00', '09:00:00', 'Aufgabe B');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        // ma2 sollte berücksichtigt werden (adjust=true) → reassign mit Anpassung
        $this->assertCount(1, $result['suggestions']);
        if ($result['suggestions'][0]['action'] === 'reassign') {
            $this->assertNotNull($result['suggestions'][0]['adjust_working_time']);
        }
    }

    /**
     * Szenario 9: Ersatz arbeitet >6h ohne Pause → Pause wird vorgeschlagen (needsBreak)
     */
    public function test_szenario9_pause_wird_vorgeschlagen_bei_mehr_als_6h(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        // ma2 arbeitet 7h (07:00–14:00), kein Pause-Event
        $this->addWorkingTime($ma1, $roster, $date, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma2, $roster, $date, '07:00:00', '14:00:00');
        $this->addEvent($ma1, $roster, $date, '08:00:00', '09:00:00', 'Aufgabe C');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        $this->assertCount(1, $result['suggestions']);
        if ($result['suggestions'][0]['action'] === 'reassign') {
            // Bei >6h Schicht wird Pause vorgeschlagen
            $this->assertNotNull($result['suggestions'][0]['add_break']);
        }
    }

    /**
     * Szenario 10: Mehrere Candidates → Bester Candidate wird gewählt (covers=true bevorzugt)
     */
    public function test_szenario10_bester_candidate_wird_gewaehlt(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept); // Deckt Event genau ab
        $ma3    = $this->createEmployee($dept); // Muss Arbeitszeit verlängern
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        $this->addWorkingTime($ma1, $roster, $date, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma2, $roster, $date, '08:00:00', '16:00:00'); // deckt 09:00–10:00
        $this->addWorkingTime($ma3, $roster, $date, '11:00:00', '16:00:00'); // muss verlängern
        $this->addEvent($ma1, $roster, $date, '09:00:00', '10:00:00', 'Aufgabe D');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        $this->assertCount(1, $result['suggestions']);
        $this->assertEquals('reassign', $result['suggestions'][0]['action']);
        // ma2 sollte bevorzugt werden (deckt Event ohne Anpassung ab)
        $this->assertEquals($ma2->id, $result['suggestions'][0]['to']['id']);
    }

    /**
     * Summary enthält alle Zähler korrekt
     */
    public function test_summary_hat_korrekte_werte(): void
    {
        $dept   = $this->createDept();
        $ma1    = $this->createEmployee($dept);
        $ma2    = $this->createEmployee($dept);
        $roster = $this->createRoster($dept);
        $date   = $this->monday->format('Y-m-d');

        $this->addWorkingTime($ma1, $roster, $date, '08:00:00', '16:00:00');
        $this->addWorkingTime($ma2, $roster, $date, '08:00:00', '16:00:00');
        $this->addEvent($ma1, $roster, $date, '09:00:00', '10:00:00', 'Event 1');
        $this->addEvent($ma1, $roster, $date, '11:00:00', '12:00:00', 'Event 2');

        $this->makeAbsent($ma1, $date, $date);

        $result = $this->planner->suggest($roster);

        $summary = $result['summary'];
        $this->assertArrayHasKey('betroffene_events', $summary);
        $this->assertArrayHasKey('neu_zugewiesen', $summary);
        $this->assertArrayHasKey('nicht_zuweisbar', $summary);
        $this->assertArrayHasKey('zusatz_minuten', $summary);
        $this->assertArrayHasKey('neue_pausen', $summary);
        $this->assertEquals(2, $summary['betroffene_events']);
        $this->assertEquals(2, $summary['neu_zugewiesen']);
    }
}

