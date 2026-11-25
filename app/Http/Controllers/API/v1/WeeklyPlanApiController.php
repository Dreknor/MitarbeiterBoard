<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Schueler;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpAufgabeStatus;
use App\Models\Wochenplan\WpPlan;
use App\Policies\WpPlanPolicy;
use App\Services\Wochenplan\WpStatusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API v1 – Wochenplan-Aufgaben eines Kindes und Status je Aufgabe (Pädagogen-App).
 *
 * Wie im Web: nur mit Tagebuch-Anbindung (Setting `wochenplan_tagebuch_anbindung`),
 * Leserecht am Wochenplan und Zugriff auf die Klasse des Kindes (`klasse_user`).
 * Abhaken nur durch Pädagog:innen – Schüler-Tokens werden schon von `api.staff` abgewiesen.
 */
class WeeklyPlanApiController extends Controller
{
    public function __construct(private WpStatusService $status)
    {
    }

    /** GET /api/v1/students/{student}/weekly-plan?date=YYYY-MM-DD */
    public function show(Request $request, Schueler $schueler): JsonResponse
    {
        $this->pruefen($request, $schueler);
        $request->validate(['date' => 'nullable|date']);

        $tag = $request->filled('date') ? Carbon::parse($request->input('date')) : now();
        $woche = $this->status->wocheFuerSchueler($schueler, $tag);

        return response()->json(['data' => [
            'student' => $woche['schueler'],
            'week' => ['start' => $woche['woche']['von'], 'end' => $woche['woche']['bis']],
            'summary' => [
                'done' => $woche['zusammenfassung']['erledigt'],
                'partial' => $woche['zusammenfassung']['teilweise'],
                'total' => $woche['zusammenfassung']['gesamt'],
            ],
            'plans' => collect($woche['plaene'])->map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'type' => $p['typ'],
                'period' => $p['zeitraum'],
                'subjects' => collect($p['faecher'])->map(fn ($f) => [
                    'id' => $f['id'],
                    'name' => $f['name'],
                    'tasks' => collect($f['aufgaben'])->map(fn ($a) => $this->aufgabe($a))->values(),
                ])->values(),
            ])->values(),
        ]]);
    }

    /** POST /api/v1/weekly-plan/tasks/{task}/status  {student_id, status: open|partial|done, note?} */
    public function store(Request $request, WpAufgabe $aufgabe): JsonResponse
    {
        $daten = $request->validate([
            'student_id' => 'required|integer|exists:schueler,id',
            'status' => ['required', Rule::in(['open', 'partial', 'done'])],
            'note' => 'nullable|string|max:255',
        ]);
        $schueler = Schueler::findOrFail($daten['student_id']);
        $this->pruefen($request, $schueler, $aufgabe);

        $status = ['open' => WpAufgabeStatus::OFFEN, 'partial' => WpAufgabeStatus::TEILWEISE, 'done' => WpAufgabeStatus::ERLEDIGT][$daten['status']];
        $eintrag = $this->status->setzen($aufgabe, $schueler, $status, $daten['note'] ?? null, $request->user());
        $eintrag?->load('user');

        return response()->json(['data' => $this->aufgabe($this->status->aufgabeMitStatus($aufgabe, $eintrag))]);
    }

    /** DELETE /api/v1/weekly-plan/tasks/{task}/status?student_id= – zurück auf „offen“, Notiz entfällt. */
    public function destroy(Request $request, WpAufgabe $aufgabe): JsonResponse
    {
        $daten = $request->validate(['student_id' => 'required|integer|exists:schueler,id']);
        $schueler = Schueler::findOrFail($daten['student_id']);
        $this->pruefen($request, $schueler, $aufgabe);

        $this->status->setzen($aufgabe, $schueler, WpAufgabeStatus::OFFEN, null, $request->user());

        return response()->json(['data' => $this->aufgabe($this->status->aufgabeMitStatus($aufgabe, null))]);
    }

    private function pruefen(Request $request, Schueler $schueler, ?WpAufgabe $aufgabe = null): void
    {
        abort_unless(WpStatusService::tagebuchAktiv(), 404, 'Die Wochenplan-Anbindung ist ausgeschaltet.');
        $user = $request->user();
        abort_unless($user->can('viewAny', WpPlan::class) && WpPlanPolicy::darfSchueler($user, $schueler), 403);

        if ($aufgabe) {
            $plan = $aufgabe->planFach?->plan;
            abort_unless($plan && $user->can('trackProgress', $plan), 403);
            abort_unless($this->status->aufgabeGiltFuer($aufgabe, $schueler), 422, 'Diese Aufgabe gehört nicht zum Wochenplan des Kindes.');
        }
    }

    private function aufgabe(array $a): array
    {
        return [
            'id' => $a['id'],
            'text' => $a['aufgabe'],
            'duration' => $a['dauer'],
            'level' => $a['niveau'],
            'link' => $a['link'],
            'from_diary' => $a['tagebuch'],
            'status' => ['offen' => 'open', 'teilweise' => 'partial', 'erledigt' => 'done'][$a['status']] ?? 'open',
            'note' => $a['notiz'],
            'updated_by' => $a['von'],
            'updated_at' => $a['am'],
        ];
    }
}
