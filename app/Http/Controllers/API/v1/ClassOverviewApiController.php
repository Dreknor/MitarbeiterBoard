<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\DiagnosticArea;
use App\Models\DiagnosticAssessment;
use App\Models\GradingDocumentationSession;
use App\Models\GradingStage;
use App\Models\Klasse;
use App\Models\Schueler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 – Klassenübersichten für die Planung: Stufenverteilung der Graduierung und
 * Diagnose-Kriterien, die in der Klasse häufig noch nicht sicher sind (Grundlage für Fördergruppen).
 */
class ClassOverviewApiController extends Controller
{
    /**
     * GET /api/v1/classes/{class}/grading/overview – wie viele Schüler auf welcher Stufe sind.
     * Stufen des Graduierungssystems der Klasse in Reihenfolge; Schüler ohne bzw. mit fremder Stufe
     * (z. B. nach Klassenwechsel) unter `without_stage` bzw. `other_stages`.
     */
    public function grading(Request $request, Klasse $klasse): JsonResponse
    {
        $this->authorize('viewClass', [Schueler::class, $klasse]);

        $students = $klasse->schueler()->orderBy('vorname')->orderBy('nachname')
            ->get(['id', 'vorname', 'nachname', 'grading_stage_id']);
        $stages = $klasse->grading_system_id
            ? GradingStage::where('grading_system_id', $klasse->grading_system_id)->orderBy('sort_order')->get()
            : collect();
        $stageIds = $stages->pluck('id')->all();

        $openSessions = $klasse->grading_system_id
            ? GradingDocumentationSession::where('klasse_id', $klasse->id)->whereNull('completed_at')->count()
            : 0;

        $brief = fn (Schueler $s) => [
            'id' => $s->id,
            'firstname' => $s->vorname,
            'lastname' => $s->nachname,
        ];

        $otherStageIds = $students->pluck('grading_stage_id')->filter()->unique()->diff($stageIds);
        $otherStages = GradingStage::whereIn('id', $otherStageIds)->get()->keyBy('id');

        return response()->json(['data' => [
            'class' => ['id' => $klasse->id, 'name' => $klasse->name],
            'grading_system' => $klasse->gradingSystem ? [
                'id' => $klasse->gradingSystem->id,
                'name' => $klasse->gradingSystem->name,
            ] : null,
            'students_total' => $students->count(),
            'open_sessions_count' => $openSessions,
            'stages' => $stages->map(function (GradingStage $stage) use ($students, $brief) {
                $onStage = $students->where('grading_stage_id', $stage->id)->values();

                return [
                    'id' => $stage->id,
                    'title' => $stage->name,
                    'level' => (int) $stage->sort_order,
                    'symbol' => $stage->symbol,
                    'badge_image_url' => $stage->image_url,
                    'count' => $onStage->count(),
                    'students' => $onStage->map($brief)->values(),
                ];
            })->values(),
            'other_stages' => $students->filter(fn ($s) => $otherStages->has($s->grading_stage_id))
                ->map(fn ($s) => $brief($s) + ['stage_title' => $otherStages[$s->grading_stage_id]->name])
                ->values(),
            'without_stage' => $students->filter(fn ($s) => !$s->grading_stage_id)->map($brief)->values(),
        ]]);
    }

    /**
     * GET /api/v1/classes/{class}/diagnostic/overview?area_id=&min_count=
     *
     * Je Kriterium die jeweils letzte Bewertung jedes Schülers der Klasse (neueste Sitzung):
     * `white` = kann es, `gray` = aktuelles Ziel, `dark_gray` = kann es noch nicht. Sortiert nach
     * „kann es nicht“ + „aktuelles Ziel“ absteigend. Kriterien, die bei weniger als `min_count`
     * Schülern (Standard 1) noch nicht sicher sind, werden weggelassen.
     */
    public function diagnostic(Request $request, Klasse $klasse): JsonResponse
    {
        $this->authorize('viewClass', [Schueler::class, $klasse]);
        abort_unless($request->user()->can('view diagnostics'), 403, 'Dafür fehlt dir die Berechtigung.');
        $data = $request->validate([
            'area_id' => ['nullable', 'integer', 'exists:diagnostic_areas,id'],
            'min_count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $minCount = (int) ($data['min_count'] ?? 1);

        $students = $klasse->schueler()->get(['id', 'vorname', 'nachname'])->keyBy('id');

        // Alle Bewertungen der Schüler, neueste Sitzung zuerst → erste je (Schüler, Kriterium) gewinnt.
        $assessments = DiagnosticAssessment::query()
            ->join('diagnostic_sessions', 'diagnostic_sessions.id', '=', 'diagnostic_assessments.diagnostic_session_id')
            ->whereIn('diagnostic_sessions.schueler_id', $students->keys())
            ->whereNotNull('diagnostic_assessments.rating')
            ->when(isset($data['area_id']), fn ($q) => $q->where('diagnostic_sessions.diagnostic_area_id', $data['area_id']))
            ->orderByDesc('diagnostic_sessions.session_date')
            ->orderByDesc('diagnostic_sessions.id')
            ->get([
                'diagnostic_assessments.diagnostic_goal_id',
                'diagnostic_assessments.rating',
                'diagnostic_sessions.schueler_id',
                'diagnostic_sessions.session_date',
            ]);

        $latest = [];
        foreach ($assessments as $a) {
            $key = $a->diagnostic_goal_id . '|' . $a->schueler_id;
            $latest[$key] ??= $a;
        }

        $byCriterion = collect($latest)->groupBy('diagnostic_goal_id');
        $criteria = \App\Models\DiagnosticGoal::with('stage.area')->whereIn('id', $byCriterion->keys())->get()->keyBy('id');

        $brief = fn ($a) => [
            'id' => (int) $a->schueler_id,
            'firstname' => $students[$a->schueler_id]->vorname ?? '',
            'lastname' => $students[$a->schueler_id]->nachname ?? '',
            'assessed_on' => $a->session_date ? \Carbon\Carbon::parse($a->session_date)->toDateString() : null,
        ];

        $rows = $byCriterion->map(function ($items, $goalId) use ($criteria, $brief) {
            $goal = $criteria[$goalId] ?? null;
            if (!$goal) {
                return null;
            }
            $counts = ['white' => 0, 'gray' => 0, 'dark_gray' => 0];
            foreach ($items as $a) {
                if (isset($counts[$a->rating])) {
                    $counts[$a->rating]++;
                }
            }

            return [
                'criterion_id' => $goal->id,
                'code' => $goal->code,
                'description' => $goal->description,
                'stage_id' => $goal->stage?->id,
                'stage_title' => $goal->stage?->name,
                'area_id' => $goal->stage?->area?->id,
                'area_title' => $goal->stage?->area?->name,
                'counts' => $counts + ['assessed' => array_sum($counts)],
                'students_not_yet' => $items->where('rating', 'dark_gray')->map($brief)->values(),
                'students_partial' => $items->where('rating', 'gray')->map($brief)->values(),
                '_sort' => [$goal->stage?->area?->sort_order ?? 0, $goal->stage?->sort_order ?? 0, $goal->sort_order ?? 0],
            ];
        })->filter()
            ->filter(fn ($r) => $r['counts']['dark_gray'] + $r['counts']['gray'] >= $minCount)
            ->sort(function ($a, $b) {
                $open = ($b['counts']['dark_gray'] <=> $a['counts']['dark_gray'])
                    ?: ($b['counts']['gray'] <=> $a['counts']['gray']);

                return $open ?: ($a['_sort'] <=> $b['_sort']);
            })
            ->map(fn ($r) => collect($r)->except('_sort')->all())
            ->values();

        return response()->json(['data' => [
            'class' => ['id' => $klasse->id, 'name' => $klasse->name],
            'students_total' => $students->count(),
            'assessed_students' => collect($latest)->pluck('schueler_id')->unique()->count(),
            'areas' => DiagnosticArea::where('active', true)->orderBy('sort_order')->get(['id', 'name'])
                ->map(fn ($a) => ['id' => $a->id, 'title' => $a->name])->values(),
            'criteria' => $rows,
        ]]);
    }
}
