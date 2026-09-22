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
     * GET /personal/mitarbeiter/{employe}/pruefung/{date?}?bis=Y-m
     * Dashboard: Auffälligkeiten, Vertragsänderungs-Banner, Soll/Ist-Gegenüberstellung.
     * Standardmäßig wird ein einzelner Monat angezeigt ({date}); über den Query-Parameter
     * "bis" kann optional ein längerer Zeitraum (Quartal, Halbjahr, Jahr, ...) betrachtet werden,
     * wobei {date} dann als Anfangsmonat des Zeitraums dient.
     */
    public function index(Request $request, int $employe, ?string $date = null): View
    {
        /** @var User $employe */
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);

        $month = $date ? Carbon::createFromFormat('Y-m', $date)->startOfMonth() : Carbon::now()->startOfMonth();

        $bisParam = $request->query('bis');
        $periodStart = $month;
        $periodEnd   = $bisParam ? Carbon::createFromFormat('Y-m', $bisParam)->startOfMonth() : $month;
        if ($periodEnd->lessThan($periodStart)) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }
        $isRange = !$periodStart->isSameMonth($periodEnd);

        $anomalies = TimesheetAnomaly::forEmploye($employe->id)
            ->forPeriodRange($periodStart, $periodEnd)
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

        // Bei Einzelmonat-Ansicht (Standardfall) wird zusätzlich das Timesheet des Monats geladen (Sperrstatus etc.).
        $timesheet = $isRange ? null : Timesheet::where('employe_id', $employe->id)
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->first();

        $balance = $this->validationService->calculateBalance($employe, $periodStart->copy()->startOfMonth(), $periodEnd->copy()->endOfMonth());

        $anomaliesByDay = $anomalies->groupBy(fn (TimesheetAnomaly $a) => $a->date?->format('Y-m-d'));

        return view('personal.timesheet-validation.index', [
            'employe'                 => $employe,
            'month'                   => $month,
            'periodStart'             => $periodStart,
            'periodEnd'               => $periodEnd,
            'isRange'                 => $isRange,
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
     * POST /personal/mitarbeiter/{employe}/pruefung/zeitraum-lauf
     * Stößt eine Neu-Prüfung für einen Mitarbeiter über einen mehrmonatigen
     * Zeitraum (z. B. Quartal, Halbjahr, Jahr) an.
     */
    public function runForEmployeeRange(Request $request, int $employe): RedirectResponse
    {
        /** @var User $employe */
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);

        $data = $request->validate([
            'von' => ['required', 'date_format:Y-m'],
            'bis' => ['required', 'date_format:Y-m', 'after_or_equal:von'],
        ]);

        $rangeStart = Carbon::createFromFormat('Y-m', $data['von'])->startOfMonth();
        $rangeEnd   = Carbon::createFromFormat('Y-m', $data['bis'])->endOfMonth();

        $anomalies = $this->validationService->runForEmployeeRange($employe, $rangeStart, $rangeEnd, auth()->user());

        return redirect()
            ->route('personal.timesheet-validation.index', ['employe' => $employe->id, 'date' => $rangeEnd->format('Y-m')])
            ->with('type', 'success')
            ->with('Meldung', sprintf(
                'Prüflauf für %s bis %s abgeschlossen: %d Auffälligkeit(en) gefunden.',
                $rangeStart->format('m.Y'), $rangeEnd->format('m.Y'), $anomalies->count()
            ));
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
     * POST /personal/abteilung/{department}/pruefung/zeitraum-lauf
     * Stößt eine Neu-Prüfung für alle aktiven Mitarbeiter einer Abteilung über
     * einen mehrmonatigen Zeitraum an.
     */
    public function runForDepartmentRange(Request $request, Group $department): RedirectResponse
    {
        $data = $request->validate([
            'von' => ['required', 'date_format:Y-m'],
            'bis' => ['required', 'date_format:Y-m', 'after_or_equal:von'],
        ]);

        $rangeStart = Carbon::createFromFormat('Y-m', $data['von'])->startOfMonth();
        $rangeEnd   = Carbon::createFromFormat('Y-m', $data['bis'])->endOfMonth();

        $results = $this->validationService->runForDepartmentRange($department, $rangeStart, $rangeEnd, auth()->user());
        $total = $results->sum(fn ($c) => $c->count());

        return redirectBack('success', sprintf(
            'Prüflauf für Abteilung "%s" (%s bis %s) abgeschlossen: %d Auffälligkeit(en) bei %d Mitarbeiter(n).',
            $department->name, $rangeStart->format('m.Y'), $rangeEnd->format('m.Y'), $total, $results->count()
        ));
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



