<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\DossierRequest;
use App\Http\Resources\API\v1\CurrentCriterionGoalResource;
use App\Http\Resources\API\v1\DiagnosticGoalResource;
use App\Http\Resources\API\v1\DiagnosticSessionResource;
use App\Http\Resources\API\v1\GradingSessionResource;
use App\Http\Resources\API\v1\PaedDiaryEntryResource;
use App\Models\Schueler;
use App\Services\Api\DossierService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1 – Bereich 5: Dossier-Export für Entwicklungs- und Elterngespräche.
 * Die Datenaufbereitung liegt im DossierService (gemeinsam mit PDF-Export in API und Web).
 */
class DossierApiController extends Controller
{
    public function __construct(private DossierService $dossier)
    {
    }

    /**
     * GET /api/v1/students/{student}/dossier?from_date=&to_date=&include_confidential=
     * Standardzeitraum: aktuelles Schuljahr bis heute.
     */
    public function show(DossierRequest $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('view', $schueler);

        $d = $this->dossier->build($schueler, $request->user(), $request);

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
                'from_date' => $d['from']->toDateString(),
                'to_date' => $d['to']->toDateString(),
            ],
            'paed_diary' => [
                'entries_count' => $d['entries']->count(),
                'by_category' => $d['by_category'],
                'entries' => PaedDiaryEntryResource::collection($d['entries']),
                'goals' => $d['diary_goals'],
            ],
            'grading' => [
                'current_stage' => $d['grading']['current_stage'],
                'stage_history' => $d['grading']['stage_history'],
                'completed_sessions' => GradingSessionResource::collection($d['grading']['completed_sessions']),
            ],
            'diagnostic' => $d['diagnostic'] ? [
                'sessions' => DiagnosticSessionResource::collection($d['diagnostic']['sessions']),
                'development_goals' => DiagnosticGoalResource::collection($d['diagnostic']['development_goals']),
                'current_criterion_goals' => CurrentCriterionGoalResource::collection($d['diagnostic']['current_criterion_goals']),
            ] : null,
            'meta' => array_merge($d['meta'], [
                'generated_at' => $d['meta']['generated_at']->toIso8601String(),
            ]),
        ]);
    }

    /**
     * GET /api/v1/students/{student}/dossier.pdf – gleiche Parameter und Rechte wie /dossier.
     */
    public function pdf(DossierRequest $request, Schueler $schueler): Response
    {
        $this->authorize('view', $schueler);

        return $this->dossier->pdfResponse($this->dossier->build($schueler, $request->user(), $request));
    }
}
