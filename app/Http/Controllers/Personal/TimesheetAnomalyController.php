<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetAnomaly;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use App\Services\Personal\TimeValidationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Arbeitspaket 4.2/4.3: Web-Controller für die Prüfengine.
 * Ermöglicht HR-Personal das Anstoßen von Neu-Prüfungen sowie das Quittieren/
 * Auflösen von Warnungen und Vertragsanpassungen direkt aus der Ansicht heraus.
 */
class TimesheetAnomalyController extends Controller
{
    public function __construct(
        private readonly PersonalScopeService $scopeService,
        private readonly TimeValidationService $validationService,
    ) {}

    /**
     * GET /personal/mitarbeiter/{employe}/pruefung/{date?}
     * Monats-Dashboard: Auffälligkeiten, Vertragsänderungs-Banner, Soll/Ist-Gegenüberstellung.
     */
    public function index(int $employe, ?string $date = null): View
    {
        /** @var User $employe */
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);

        $month = $date ? Carbon::createFromFormat('Y-m', $date)->startOfMonth() : Carbon::now()->startOfMonth();

        $anomalies = TimesheetAnomaly::forEmploye($employe->id)
            ->forPeriod($month->month, $month->year)
            ->with(['resolvedBy', 'relatedEmployment'])
            ->orderByDesc('severity')
            ->orderBy('date')
            ->get();

        $contractChangeAnomalies = $anomalies->filter(
            fn (TimesheetAnomaly $a) => in_array($a->rule_type->value, ['CONTRACT_CHANGE_IN_PERIOD', 'RETROACTIVE_CONTRACT_CHANGE'], true)
                && !$a->is_resolved
        );

        $otherAnomalies = $anomalies->reject(
            fn (TimesheetAnomaly $a) => in_array($a->rule_type->value, ['CONTRACT_CHANGE_IN_PERIOD', 'RETROACTIVE_CONTRACT_CHANGE'], true)
        );

        $timesheet = Timesheet::where('employe_id', $employe->id)
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->first();

        $balance = $this->validationService->calculateMonthlyBalance($employe, $month);

        $anomaliesByDay = $anomalies->groupBy(fn (TimesheetAnomaly $a) => $a->date?->format('Y-m-d'));

        return view('personal.timesheet-validation.index', [
            'employe'                 => $employe,
            'month'                   => $month,
            'timesheet'               => $timesheet,
            'anomalies'               => $otherAnomalies,
            'contractChangeAnomalies' => $contractChangeAnomalies,
            'anomaliesByDay'          => $anomaliesByDay,
            'balance'                 => $balance,
        ]);
    }

    /**
     * POST /personal/mitarbeiter/{employe}/pruefung/{date}/lauf
     * Stößt eine Neu-Prüfung für einen einzelnen Mitarbeiter an.
     */
    public function runForEmployee(Request $request, int $employe, string $date): RedirectResponse
    {
        /** @var User $employe */
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);
        $month = Carbon::createFromFormat('Y-m', $date)->startOfMonth();

        $anomalies = $this->validationService->runForEmployee($employe, $month, auth()->user());

        return redirect()
            ->route('personal.timesheet-validation.index', ['employe' => $employe->id, 'date' => $month->format('Y-m')])
            ->with('type', 'success')
            ->with('Meldung', sprintf('Prüflauf abgeschlossen: %d Auffälligkeit(en) gefunden.', $anomalies->count()));
    }

    /**
     * POST /personal/orgchart-abteilung/{department}/pruefung/{date}/lauf
     * Stößt eine Neu-Prüfung für alle aktiven Mitarbeiter einer Abteilung an.
     */
    public function runForDepartment(Request $request, Group $department, string $date): RedirectResponse
    {
        $month = Carbon::createFromFormat('Y-m', $date)->startOfMonth();
        $results = $this->validationService->runForDepartment($department, $month, auth()->user());

        $total = $results->sum(fn ($c) => $c->count());

        return redirectBack('success', sprintf('Prüflauf für Abteilung "%s" abgeschlossen: %d Auffälligkeit(en) bei %d Mitarbeiter(n).', $department->name, $total, $results->count()));
    }

    /**
     * PATCH /personal/pruefung/anomalien/{anomaly}/quittieren
     * Quittiert/löst eine Auffälligkeit (Warnung, Vertragsänderung, ...).
     */
    public function resolve(Request $request, TimesheetAnomaly $anomaly): RedirectResponse
    {
        $employe = $this->scopeService->visibleEmployees()->findOrFail($anomaly->employe_id);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $anomaly->resolve(auth()->user(), $data['comment'] ?? null);

        // Falls alle Anomalien des Monats quittiert sind, kann das Timesheet als geprüft markiert werden.
        $openCount = TimesheetAnomaly::forEmploye($employe->id)
            ->forPeriod($anomaly->month, $anomaly->year)
            ->unresolved()
            ->count();

        if ($openCount === 0) {
            $timesheet = Timesheet::where('employe_id', $employe->id)
                ->where('year', $anomaly->year)
                ->where('month', $anomaly->month)
                ->first();

            $timesheet?->markReviewed(auth()->user());
        }

        return redirect()
            ->route('personal.timesheet-validation.index', ['employe' => $employe->id, 'date' => sprintf('%04d-%02d', $anomaly->year, $anomaly->month)])
            ->with('type', 'success')
            ->with('Meldung', 'Auffälligkeit wurde quittiert.');
    }
}

