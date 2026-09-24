<?php

namespace App\Services\Api;

use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticSession;
use App\Models\GradingDocumentationSession;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use App\Models\SchuelerGradingHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * API v1: Gemeinsame Datenabfragen für Schüler-View und Dossier.
 * Alle Abfragen nutzen Eager Loading, um N+1-Probleme zu vermeiden.
 */
class StudentDataService
{
    /**
     * Aktuelle Graduierungsstufe inkl. Datum der Vergabe.
     */
    public function currentStage(Schueler $schueler): ?array
    {
        $stage = $schueler->grading_stage;
        if (!$stage) {
            return null;
        }

        $achievedAt = SchuelerGradingHistory::where('schueler_id', $schueler->id)
            ->where('grading_stage_id', $stage->id)
            ->orderByDesc('created_at')
            ->value('created_at');

        return [
            'id' => $stage->id,
            'title' => $stage->name,
            'level' => (int) $stage->sort_order,
            'symbol' => $stage->symbol,
            'badge_image_url' => $stage->image_url,
            'achieved_at' => $achievedAt ? Carbon::parse($achievedAt)->toDateString() : null,
        ];
    }

    /**
     * Offene individuelle Graduierungs-Session (bevorzugt die des Benutzers).
     */
    public function openGradingSession(Schueler $schueler, User $user): ?GradingDocumentationSession
    {
        return GradingDocumentationSession::where('type', 'individual')
            ->where('schueler_id', $schueler->id)
            ->whereNull('completed_at')
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Graduierungshistorie (Stufenwechsel) eines Schülers.
     */
    public function gradingHistory(Schueler $schueler, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return SchuelerGradingHistory::with(['stage', 'previous_stage', 'changed_by_user:id,name'])
            ->where('schueler_id', $schueler->id)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SchuelerGradingHistory $h) => [
                'id' => $h->id,
                'changed_at' => $h->created_at ? Carbon::parse($h->created_at)->toIso8601String() : null,
                'stage_id' => $h->grading_stage_id,
                'stage_title' => $h->stage?->name,
                'stage_badge_url' => $h->stage?->image_url,
                'previous_stage_id' => $h->previous_grading_stage_id,
                'previous_stage_title' => $h->previous_stage?->name,
                'changed_by_name' => $h->changed_by_user?->name,
                'paed_diary_entry_id' => $h->paed_diary_entry_id,
            ])->values();
    }

    /**
     * Abgeschlossene Graduierungs-Sessions (individuell für den Schüler oder Gruppen-Sessions
     * der aktuellen Klasse) inkl. der Bewertungen dieses Schülers.
     */
    public function completedGradingSessions(Schueler $schueler, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return GradingDocumentationSession::query()
            ->where(function ($q) use ($schueler) {
                $q->where(fn ($q2) => $q2->where('type', 'individual')->where('schueler_id', $schueler->id))
                    ->orWhere(fn ($q2) => $q2->where('type', 'group')->where('klasse_id', $schueler->klasse_id));
            })
            ->whereNotNull('completed_at')
            ->when($from, fn ($q) => $q->where('completed_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('completed_at', '<=', $to->copy()->endOfDay()))
            ->with([
                'gradingSystem:id,name',
                'gradingSystem.questions' => fn ($q) => $q->orderBy('sort_order'),
                'user:id,name',
                'studentAnswers' => fn ($q) => $q->where('schueler_id', $schueler->id),
                'teacherAssessments' => fn ($q) => $q->where('schueler_id', $schueler->id),
                'coachingNotes' => fn ($q) => $q->where('schueler_id', $schueler->id),
            ])
            ->orderByDesc('completed_at')
            ->get();
    }

    /**
     * Individuelle Entwicklungsziele.
     */
    public function developmentGoals(Schueler $schueler, bool $activeOnly = true, bool $includeArchived = false): Collection
    {
        return DiagnosticDevelopmentGoal::with(['area:id,name', 'creator:id,name'])
            ->forSchueler($schueler->id)
            ->when($activeOnly, fn ($q) => $q->active())
            ->when(!$activeOnly && !$includeArchived, fn ($q) => $q->notArchived())
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Im Web-Frontend als "aktuelles Ziel" markierte Katalogkriterien.
     */
    public function currentCriterionGoals(Schueler $schueler): Collection
    {
        return DiagnosticAssessment::where('is_current_goal', true)
            ->whereHas('session', fn ($q) => $q->where('schueler_id', $schueler->id))
            ->with(['goal.stage.area', 'session'])
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->goal?->stage?->area?->sort_order ?? 0) <=> ($b->goal?->stage?->area?->sort_order ?? 0),
                fn ($a, $b) => ($a->goal?->stage?->sort_order ?? 0) <=> ($b->goal?->stage?->sort_order ?? 0),
                fn ($a, $b) => ($a->goal?->code ?? '') <=> ($b->goal?->code ?? ''),
            ])
            ->values();
    }

    public function lastAssessmentDate(Schueler $schueler): ?string
    {
        $date = DiagnosticSession::where('schueler_id', $schueler->id)->max('session_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * Delta-Abfragen: ISO-8601-Zeitpunkt (beliebige Zeitzone) in die Zeitzone der Anwendung umrechnen,
     * da Zeitstempel in der Datenbank ohne Zeitzone gespeichert sind.
     */
    public function sinceTimestamp(string $updatedSince): Carbon
    {
        return Carbon::parse($updatedSince)->setTimezone(config('app.timezone'));
    }

    /**
     * Konfliktschutz: true, wenn der Client einen veralteten Stand (expected_updated_at) übermittelt.
     * Verglichen wird sekundengenau (ISO-8601 mit beliebiger Zeitzone).
     */
    public function isStale(\Illuminate\Database\Eloquent\Model $model, ?string $expectedUpdatedAt): bool
    {
        if ($expectedUpdatedAt === null || $expectedUpdatedAt === '') {
            return false;
        }

        $current = $model->updated_at;
        if (!$current) {
            return false;
        }

        return Carbon::parse($expectedUpdatedAt)->getTimestamp() !== $current->getTimestamp();
    }

    /**
     * Tagebucheinträge eines Schülers (vertrauliche Einträge gemäß Rechten gefiltert).
     */
    public function diaryEntriesQuery(Schueler $schueler, User $user, bool $includeConfidential = true)
    {
        return PaedDiaryEntry::query()
            ->forSchueler($schueler->id)
            ->confidentialFilter($user, $includeConfidential)
            ->with(['category:id,name,color', 'user:id,name', 'schueler:schueler.id'])
            ->orderByDesc('datum')
            ->orderByDesc('id');
    }
}
