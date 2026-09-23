<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\StudentViewRequest;
use App\Http\Resources\API\v1\CurrentCriterionGoalResource;
use App\Http\Resources\API\v1\DiagnosticGoalResource;
use App\Http\Resources\API\v1\StudentViewResource;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use App\Services\Api\StudentDataService;

/**
 * API v1 – Bereich 1: Zentrale Schüler-View.
 */
class StudentViewApiController extends Controller
{
    public function __construct(private StudentDataService $data)
    {
    }

    /**
     * GET /api/v1/students/{student}/view
     */
    public function show(StudentViewRequest $request, Schueler $schueler)
    {
        $this->authorize('view', $schueler);

        $user = $request->user();
        $diaryLimit = (int) $request->input('diary_limit', 10);
        $includeArchived = $request->boolean('include_archived_goals');
        $canViewDiagnostics = $user->can('viewDiagnostics', $schueler);

        $schueler->loadMissing(['klasse:id,name,grading_system_id', 'grading_stage', 'klasse.gradingSystem:id,name']);

        // Graduierung
        $openSession = $this->data->openGradingSession($schueler, $user);
        $gradingOverview = [
            'grading_system' => $schueler->klasse?->gradingSystem ? [
                'id' => $schueler->klasse->gradingSystem->id,
                'name' => $schueler->klasse->gradingSystem->name,
            ] : null,
            'current_stage' => $this->data->currentStage($schueler),
            'has_open_session' => $openSession !== null,
            'open_session_id' => $openSession?->id,
            'open_session_is_own' => $openSession ? (int) $openSession->user_id === (int) $user->id : false,
        ];

        // Diagnose (nur mit "view diagnostics")
        $diagnosticOverview = null;
        if ($canViewDiagnostics) {
            $activeGoals = $this->data->developmentGoals($schueler, true);
            $goalsForList = $includeArchived
                ? $this->data->developmentGoals($schueler, false, true)
                : $activeGoals;

            $diagnosticOverview = [
                'active_goals_count' => $activeGoals->count(),
                'active_goals' => DiagnosticGoalResource::collection($goalsForList),
                'current_criterion_goals' => CurrentCriterionGoalResource::collection($this->data->currentCriterionGoals($schueler)),
                'last_assessment_date' => $this->data->lastAssessmentDate($schueler),
            ];
        }

        // Tagebuch
        $entries = $diaryLimit > 0
            ? $this->data->diaryEntriesQuery($schueler, $user)->limit($diaryLimit)->get()
            : collect();

        return (new StudentViewResource($schueler))->withSections([
            'grading_overview' => $gradingOverview,
            'diagnostic_overview' => $diagnosticOverview,
            'recent_paed_diary_entries' => $entries,
            'permissions' => [
                'can_edit' => $user->can('update', $schueler),
                'can_view_diagnostics' => $canViewDiagnostics,
                'can_change_grading_stage' => $user->can('changeGradingStage', $schueler),
                'can_view_confidential_entries' => $user->can('viewConfidential', PaedDiaryEntry::class),
            ],
        ]);
    }
}
