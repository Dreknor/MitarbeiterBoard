<?php

namespace Tests\Traits;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticStage;
use App\Models\Group;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use Carbon\Carbon;

trait CreatesTestData
{
    /**
     * Erstellt eine Gruppe/Abteilung.
     *
     * @param  bool  $needsRoster  true = Dienstplan-Abteilung, false = Beratungsgruppe
     */
    protected function createDepartment(bool $needsRoster = false): Group
    {
        return Group::create([
            'name'       => 'Testgruppe ' . uniqid(),
            'creator_id' => $this->getOrCreateTestUser()->id,
            'needsRoster' => $needsRoster,
        ]);
    }

    /**
     * Erstellt einen Dienstplan mit zugehörigen Events für eine Abteilung.
     *
     * @param  Group       $dept
     * @param  Carbon|null $startDate
     * @return Roster
     */
    protected function createRosterWithEvents(Group $dept, ?Carbon $startDate = null): Roster
    {
        $startDate ??= Carbon::now()->startOfWeek();

        /** @var Roster $roster */
        $roster = Roster::create([
            'start_date'    => $startDate,
            'department_id' => $dept->id,
            'type'          => 'weekly',
            'published'     => false,
        ]);

        // Zwei Beispiel-Events erstellen
        $user = $this->getOrCreateTestUser();

        RosterEvents::create([
            'roster_id' => $roster->id,
            'user_id'   => $user->id,
            'date'      => $startDate->copy()->addDay(),
            'start'     => '08:00',
            'end'       => '16:00',
        ]);

        return $roster;
    }

    /**
     * Erstellt einen Schüler, optional in einer vorhandenen Klasse.
     *
     * @param  Klasse|null $klasse
     * @return Schueler
     */
    protected function createSchuelerInKlasse(?Klasse $klasse = null): Schueler
    {
        $klasse ??= $this->createKlasse();

        return Schueler::create([
            'vorname'   => fake()->firstName(),
            'nachname'  => fake()->lastName(),
            'klasse_id' => $klasse->id,
        ]);
    }

    /**
     * Erstellt eine Klasse.
     */
    protected function createKlasse(): Klasse
    {
        return Klasse::create([
            'name'    => 'Testklasse ' . uniqid(),
            'kuerzel' => strtoupper(substr(uniqid(), -3)),
        ]);
    }

    /**
     * Erstellt ein vollständiges Diagnostik-Setup (Area + Stages + Goals).
     *
     * @return array{area: DiagnosticArea, stages: DiagnosticStage[], goals: DiagnosticGoal[]}
     */
    protected function createDiagnosticSetup(): array
    {
        /** @var DiagnosticArea $area */
        $name = 'Testbereich ' . uniqid();
        $area = DiagnosticArea::create([
            'name'       => $name,
            'slug'       => \Illuminate\Support\Str::slug($name),
            'sort_order' => 1,
            'active'     => true,
        ]);

        $stages = [];
        $goals  = [];

        for ($i = 1; $i <= 2; $i++) {
            /** @var DiagnosticStage $stage */
            $stage = DiagnosticStage::create([
                'diagnostic_area_id' => $area->id,
                'name'               => "Stufe $i",
                'code'               => "S$i",
                'sort_order'         => $i,
            ]);

            $stages[] = $stage;

            for ($j = 1; $j <= 2; $j++) {
                $goal = \App\Models\DiagnosticGoal::create([
                    'diagnostic_stage_id' => $stage->id,
                    'code'                => "Z$i.$j",
                    'description'         => "Ziel $i.$j Beschreibung",
                    'sort_order'          => $j,
                ]);
                $goals[] = $goal;
            }
        }

        return compact('area', 'stages', 'goals');
    }

    /**
     * Erstellt einen WpPlan des angegebenen Typs.
     *
     * @param  string  $type  'vorlage' | 'klassenplan' | 'schuelerplan'
     */
    protected function createWpPlan(string $type = 'klassenplan'): WpPlan
    {
        $klasse       = $this->createKlasse();
        $formatvorlage = WpFormatvorlage::create([
            'name'       => 'Standardvorlage',
            'created_by' => $this->getOrCreateTestUser()->id,
        ]);

        $base = [
            'name'            => 'Testplan ' . uniqid(),
            'gueltig_von'     => now()->startOfWeek(),
            'gueltig_bis'     => now()->endOfWeek(),
            'klasse_id'       => $klasse->id,
            'formatvorlage_id'=> $formatvorlage->id,
            'created_by'      => $this->getOrCreateTestUser()->id,
            'is_vorlage'      => false,
        ];

        return match ($type) {
            'vorlage' => WpPlan::create(array_merge($base, [
                'is_vorlage'   => true,
                'vorlage_name' => 'Mustervorlage',
                'klasse_id'    => null,
            ])),
            'schuelerplan' => (function () use ($base, $klasse) {
                $klassenplan = WpPlan::create($base);
                $schueler    = $this->createSchuelerInKlasse($klasse);

                return WpPlan::create(array_merge($base, [
                    'name'           => 'Schülerplan ' . uniqid(),
                    'schueler_id'    => $schueler->id,
                    'parent_plan_id' => $klassenplan->id,
                ]));
            })(),
            default => WpPlan::create($base), // 'klassenplan'
        };
    }

    // ─── Interne Hilfsmethode ─────────────────────────────────────────────────

    /** Gibt einen gecachten Test-User zurück oder erstellt ihn einmalig. */
    private ?User $testUser = null;

    private function getOrCreateTestUser(): User
    {
        if ($this->testUser === null || ! $this->testUser->exists) {
            $this->testUser = User::factory()->create();
        }

        return $this->testUser;
    }
}

