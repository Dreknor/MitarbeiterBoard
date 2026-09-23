<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\DossierRequest;
use App\Http\Resources\API\v1\CurrentCriterionGoalResource;
use App\Http\Resources\API\v1\DiagnosticGoalResource;
use App\Http\Resources\API\v1\DiagnosticSessionResource;
use App\Http\Resources\API\v1\GradingSessionResource;
use App\Http\Resources\API\v1\PaedDiaryEntryResource;
use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticSession;
use App\Models\PaedDiaryGoal;
use App\Models\Schueler;
use App\Services\Api\StudentDataService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * API v1 – Bereich 5: Dossier-Export für Entwicklungs- und Elterngespräche.
 */
class DossierApiController extends Controller
{
    public function __construct(private StudentDataService $data)
    {
    }

    /**
     * GET /api/v1/students/{student}/dossier?from_date=&to_date=&include_confidential=
     * Standardzeitraum: aktuelles Schuljahr bis heute.
     */
    public function show(DossierRequest $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('view', $schueler);

        $user = $request->user();
        $from = $request->filled('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : Carbon::parse(config('config.schuljahresbeginn'))->startOfDay();
        $to = $request->filled('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            $from = $to->copy()->startOfDay();
        }

        $includeConfidentialRequested = $request->has('include_confidential') ? $request->boolean('include_confidential') : true;
        $canViewConfidential = $user->canViewConfidentialDiaryEntries();
        $canViewDiagnostics = $user->can('viewDiagnostics', $schueler);

        $schueler->loadMissing(['klasse:id,name', 'grading_stage']);

        // Tagebuch
        $entries = $this->data->diaryEntriesQuery($schueler, $user, $includeConfidentialRequested)
            ->whereBetween('datum', [$from->toDateString(), $to->format('Y-m-d 23:59:59')])
            ->reorder()
            ->orderBy('datum')
            ->orderBy('id')
            ->get();

        $byCategory = $entries->groupBy(fn ($e) => $e->category_id ?? 0)->map(fn ($group) => [
            'category_id' => $group->first()->category_id,
            'category_name' => $group->first()->category?->name ?? 'Ohne Kategorie',
            'category_color' => $group->first()->category?->color,
            'count' => $group->count(),
        ])->values();

        $diaryGoals = PaedDiaryGoal::with(['user:id,name', 'achievedByUser:id,name'])
            ->where('schueler_id', $schueler->id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('achieved_at', [$from, $to])
                    ->orWhereNull('achieved_at');
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'goal_text' => $g->goal_text,
                'created_at' => $g->created_at?->toIso8601String(),
                'created_by_name' => $g->user?->name,
                'achieved_at' => $g->achieved_at?->toIso8601String(),
                'achieved_by_name' => $g->achievedByUser?->name,
            ]);

        // Graduierung
        $grading = [
            'current_stage' => $this->data->currentStage($schueler),
            'stage_history' => $this->data->gradingHistory($schueler, $from, $to),
            'completed_sessions' => GradingSessionResource::collection($this->data->completedGradingSessions($schueler, $from, $to)),
        ];

        // Diagnose
        $diagnostic = null;
        if ($canViewDiagnostics) {
            $sessions = DiagnosticSession::where('schueler_id', $schueler->id)
                ->whereBetween('session_date', [$from->toDateString(), $to->format('Y-m-d 23:59:59')])
                ->with(['area:id,name', 'user:id,name', 'stageNotes.stage:id,name', 'assessments', 'developmentGoals.area:id,name', 'developmentGoals.creator:id,name'])
                ->orderBy('session_date')
                ->get();

            $goals = DiagnosticDevelopmentGoal::with(['area:id,name', 'creator:id,name'])
                ->forSchueler($schueler->id)
                ->notArchived()
                ->where(function ($q) use ($from, $to) {
                    $q->whereIn('status', DiagnosticDevelopmentGoal::ACTIVE_STATUSES)
                        ->orWhereBetween('completed_at', [$from->toDateString(), $to->format('Y-m-d 23:59:59')])
                        ->orWhereBetween('created_at', [$from, $to]);
                })
                ->orderBy('target_date')
                ->get();

            $diagnostic = [
                'sessions' => DiagnosticSessionResource::collection($sessions),
                'development_goals' => DiagnosticGoalResource::collection($goals),
                'current_criterion_goals' => CurrentCriterionGoalResource::collection($this->data->currentCriterionGoals($schueler)),
            ];
        }

        return response()->json([
            'student' => [
                'id' => $schueler->id,
                'firstname' => $schueler->vorname,
                'lastname' => $schueler->nachname,
                'class_id' => $schueler->klasse_id,
                'class_name' => $schueler->klasse?->name,
                'date_of_birth' => $schueler->geburtsdatum?->toDateString(),
            ],
            'period' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
            'paed_diary' => [
                'entries_count' => $entries->count(),
                'by_category' => $byCategory,
                'entries' => PaedDiaryEntryResource::collection($entries),
                'goals' => $diaryGoals,
            ],
            'grading' => $grading,
            'diagnostic' => $diagnostic,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'generated_by' => $user->name,
                // Vertrauliche Einträge fremder Autoren nur mit Sonderrecht
                'includes_confidential' => $includeConfidentialRequested && $canViewConfidential,
                'includes_diagnostics' => $canViewDiagnostics,
            ],
        ]);
    }
}
