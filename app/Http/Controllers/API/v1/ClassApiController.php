<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\v1\ClassResource;
use App\Http\Resources\API\v1\ClassStudentResource;
use App\Models\Klasse;
use App\Models\Schueler;
use Illuminate\Http\Request;

/**
 * API v1 – Bereich 0: Klassen-Kontext & Schülerauswahl.
 */
class ClassApiController extends Controller
{
    /**
     * GET /api/v1/classes
     * Zugewiesene Klassen der Lehrkraft (mit ?all=true alle Klassen bei klassenübergreifenden Rechten)
     * sowie die eigenen Lerngruppen (Klassen-Gruppen des Tagebuchs).
     */
    public function index(Request $request)
    {
        $request->validate(['all' => ['sometimes', 'in:true,false,1,0']]);
        $user = $request->user();

        $showAll = $request->boolean('all') && $user->canAccessAllStudents();

        $classes = Klasse::query()
            ->when(!$showAll, fn ($q) => $q->forTeacher($user->id))
            ->withCount('schueler as students_count')
            ->orderBy('name')
            ->get();

        $groups = $user->paed_diary_class_groups()
            ->with('klassen:id')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'class_ids' => $g->klassen->pluck('id')->map(fn ($id) => (int) $id)->values(),
            ]);

        return ClassResource::collection($classes)->additional([
            'learning_groups' => $groups,
        ]);
    }

    /**
     * GET /api/v1/classes/{class}/students
     * Klassenliste mit Kompakt-Status (Graduierung, aktive Ziele, Tagebucheinträge der letzten Tage).
     */
    public function students(Request $request, Klasse $klasse)
    {
        $this->authorize('viewClass', [Schueler::class, $klasse]);

        $request->validate(['recent_days' => ['sometimes', 'integer', 'min:1', 'max:90']]);
        $recentDays = (int) $request->input('recent_days', 14);
        $user = $request->user();
        $canViewDiagnostics = $user->can('view diagnostics');
        $since = now()->subDays($recentDays - 1)->toDateString();

        $students = $klasse->schueler()
            ->with('grading_stage')
            ->withCount([
                'paedDiaryEntries as recent_diary_entries_count' => fn ($q) => $q
                    ->where('datum', '>=', $since)
                    ->confidentialFilter($user),
            ])
            ->when($canViewDiagnostics, fn ($q) => $q->withCount([
                'developmentGoals as active_diagnostic_goals_count' => fn ($g) => $g->active(),
            ]))
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return ClassStudentResource::collection($students)->additional([
            'meta' => [
                'class' => ['id' => $klasse->id, 'name' => $klasse->name],
                'recent_days' => $recentDays,
            ],
        ]);
    }
}
