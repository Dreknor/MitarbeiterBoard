<?php

namespace App\Services;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticGoal;
use App\Models\DiagnosticSession;
use App\Models\DiagnosticStage;
use App\Models\DiagnosticStageNote;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class DiagnosticService
{
    /**
     * Holt offene Session oder erstellt neue
     * Wirft Exception wenn bereits eine offene Session existiert
     *
     * @throws Exception
     */
    public function getOrCreateSession(Schueler $schueler, DiagnosticArea $area, User $user): DiagnosticSession
    {
        // Prüfen ob bereits eine offene Session existiert
        $existingSession = DiagnosticSession::where('schueler_id', $schueler->id)
            ->where('diagnostic_area_id', $area->id)
            ->where('is_completed', false)
            ->first();

        if ($existingSession) {
            return $existingSession;
        }

        // Neue Session erstellen
        return DiagnosticSession::create([
            'schueler_id' => $schueler->id,
            'diagnostic_area_id' => $area->id,
            'user_id' => $user->id,
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'is_completed' => false,
        ]);
    }

    /**
     * Prüft ob neue Session gestartet werden kann
     */
    public function canStartNewSession(Schueler $schueler, DiagnosticArea $area): bool
    {
        return !DiagnosticSession::where('schueler_id', $schueler->id)
            ->where('diagnostic_area_id', $area->id)
            ->where('is_completed', false)
            ->exists();
    }

    /**
     * Holt die letzten N Bewertungen für ein Ziel eines Schülers (aufsteigend nach Datum)
     */
    public function getHistoricalData(DiagnosticGoal $goal, Schueler $schueler, int $limit = 3): array
    {
        $assessments = DiagnosticAssessment::where('diagnostic_goal_id', $goal->id)
            ->whereHas('session', function ($query) use ($schueler) {
                $query->where('schueler_id', $schueler->id)
                    ->where('is_completed', true);
            })
            ->with('session:id,session_date')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse() // Umkehren für aufsteigende Reihenfolge
            ->values();

        return $assessments->map(function ($assessment) {
            return [
                'rating' => $assessment->rating,
                'date' => $assessment->session->session_date->format('d.m.Y'),
                'saved_at' => $assessment->saved_at,
                'color' => $this->getRatingColor($assessment->rating),
                'rating_text' => $this->getRatingText($assessment->rating),
            ];
        })->toArray();
    }

    /**
     * Gibt Farbe für Rating zurück
     */
    private function getRatingColor(?string $rating): string
    {
        return match($rating) {
            'white' => '#ffffff',
            'gray' => '#cccccc',
            'dark_gray' => '#666666',
            default => '#f0f0f0',
        };
    }

    /**
     * Gibt Text für Rating zurück
     */
    private function getRatingText(?string $rating): string
    {
        return match($rating) {
            'white' => 'Kann es',
            'gray' => 'Aktuelles Ziel',
            'dark_gray' => 'Kann es nicht',
            default => 'Nicht bewertet',
        };
    }

    /**
     * Speichert oder aktualisiert eine Bewertung
     */
    public function saveAssessment(DiagnosticSession $session, DiagnosticGoal $goal, ?string $rating): DiagnosticAssessment
    {
        return DiagnosticAssessment::updateOrCreate(
            [
                'diagnostic_session_id' => $session->id,
                'diagnostic_goal_id' => $goal->id,
            ],
            [
                'rating' => $rating,
                'saved_at' => now(),
            ]
        );
    }

    /**
     * Speichert Stufen-Notiz
     */
    public function saveStageNote(DiagnosticSession $session, DiagnosticStage $stage, ?string $note): DiagnosticStageNote
    {
        return DiagnosticStageNote::updateOrCreate(
            [
                'diagnostic_session_id' => $session->id,
                'diagnostic_stage_id' => $stage->id,
            ],
            [
                'notes' => $note,
            ]
        );
    }

    /**
     * Schließt Session ab
     */
    public function completeSession(DiagnosticSession $session): bool
    {
        return $session->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Öffnet abgeschlossene Session wieder (nur für Admins)
     */
    public function reopenSession(DiagnosticSession $session): bool
    {
        return $session->update([
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }

    /**
     * Sammelt alle aktuellen Ziele für einen Schüler
     */
    public function getCurrentGoalsForStudent(Schueler $schueler): array
    {
        $currentGoals = DiagnosticAssessment::where('is_current_goal', true)
            ->whereHas('session', function ($query) use ($schueler) {
                $query->where('schueler_id', $schueler->id);
            })
            ->with([
                'goal.stage.area',
                'session'
            ])
            ->get();

        // Gruppieren nach Bereich und Stufe
        $grouped = [];
        foreach ($currentGoals as $assessment) {
            $area = $assessment->goal->stage->area;
            $stage = $assessment->goal->stage;

            if (!isset($grouped[$area->id])) {
                $grouped[$area->id] = [
                    'area' => $area,
                    'stages' => [],
                ];
            }

            if (!isset($grouped[$area->id]['stages'][$stage->id])) {
                $grouped[$area->id]['stages'][$stage->id] = [
                    'stage' => $stage,
                    'goals' => [],
                ];
            }

            $grouped[$area->id]['stages'][$stage->id]['goals'][] = [
                'goal' => $assessment->goal,
                'assessment' => $assessment,
            ];
        }

        return array_values($grouped);
    }

    /**
     * Holt alle Sessions für einen Schüler in einem Bereich (sortiert nach Datum)
     */
    public function getSessionsForSchuelerAndArea(Schueler $schueler, DiagnosticArea $area, bool $completedOnly = true)
    {
        $query = DiagnosticSession::where('schueler_id', $schueler->id)
            ->where('diagnostic_area_id', $area->id);

        if ($completedOnly) {
            $query->where('is_completed', true);
        }

        return $query->orderBy('session_date', 'asc')->get();
    }

    /**
     * Berechnet Fortschritt für Chart (Anzahl beherrschter Ziele pro Session)
     */
    public function calculateProgress(Schueler $schueler, DiagnosticArea $area): array
    {
        $sessions = $this->getSessionsForSchuelerAndArea($schueler, $area, true);

        $progressData = [];
        foreach ($sessions as $session) {
            $whiteCount = $session->assessments()->where('rating', 'white')->count();
            $totalCount = $session->assessments()->count();

            $progressData[] = [
                'date' => $session->session_date->format('Y-m-d'),
                'white_count' => $whiteCount,
                'total_count' => $totalCount,
                'percentage' => $totalCount > 0 ? round(($whiteCount / $totalCount) * 100, 2) : 0,
            ];
        }

        return $progressData;
    }
}

