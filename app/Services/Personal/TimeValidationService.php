<?php

namespace App\Services\Personal;

use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetAnomaly;
use App\Models\User;
use App\Notifications\Push;
use App\Services\Personal\Validation\Rules\ContractChangeInPeriodRule;
use App\Services\Personal\Validation\Rules\MissingClockOutRule;
use App\Services\Personal\Validation\Rules\OverlapCheckRule;
use App\Services\Personal\Validation\Rules\RetroactiveContractChangeRule;
use App\Services\Personal\Validation\Rules\RosterDeviationRule;
use App\Services\Personal\Validation\Rules\VacationConflictRule;
use App\Services\Personal\Validation\ValidationRuleInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Zentrale Prüfengine (Arbeitspaket 2.1) für Zeiterfassung, Dienstpläne und
 * Vertragsänderungen. Orchestriert die entkoppelten Regel-Klassen (Pipeline-Muster,
 * Arbeitspaket 2.2) und persistiert die Ergebnisse in `timesheet_anomalies`.
 */
class TimeValidationService
{
    /** @var ValidationRuleInterface[] */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new OverlapCheckRule(),
            new RosterDeviationRule(),
            new VacationConflictRule(),
            new MissingClockOutRule(),
            new ContractChangeInPeriodRule(),
            new RetroactiveContractChangeRule(),
        ];
    }

    /**
     * Führt alle Prüfregeln für einen Mitarbeiter im angegebenen Monat aus und
     * persistiert die gefundenen Auffälligkeiten. Bereits bestehende, unquittierte
     * automatisch erzeugte Anomalien desselben Monats werden vor dem Neu-Lauf entfernt,
     * um Duplikate zu vermeiden; bereits quittierte (resolved) Anomalien bleiben erhalten.
     */
    public function runForEmployee(User $employe, Carbon $month, ?User $triggeredBy = null, bool $notify = true): Collection
    {
        $created = $this->runMonthForEmployee($employe, $month, $triggeredBy);

        if ($notify) {
            $this->notifyEmployeeAboutMissingEntries($employe, $created);
        }

        return $created;
    }

    /**
     * Erweiterung: Führt die Prüfengine für einen Mitarbeiter über einen Zeitraum
     * aus, der mehrere Monate umfassen kann (z. B. Quartal, Halbjahr, Jahr).
     * Intern wird Monat für Monat geprüft (wichtig für die tagesgenaue Soll-Zeit-
     * Ermittlung und die monatsbasierte Ablage in `timesheet_anomalies`); die
     * Erinnerungs-Benachrichtigung an den Mitarbeiter wird dabei nur einmal für
     * den gesamten Zeitraum verschickt (kein Spam pro Monat).
     */
    public function runForEmployeeRange(User $employe, Carbon $rangeStart, Carbon $rangeEnd, ?User $triggeredBy = null, bool $notify = true): Collection
    {
        $created = new Collection();

        foreach ($this->monthsBetween($rangeStart, $rangeEnd) as $month) {
            $created = $created->merge($this->runMonthForEmployee($employe, $month, $triggeredBy));
        }

        if ($notify) {
            $this->notifyEmployeeAboutMissingEntries($employe, $created);
        }

        Log::info('Prüfengine: Zeitraum-Prüflauf abgeschlossen', [
            'employe'      => $employe->id,
            'von'          => $rangeStart->format('Y-m'),
            'bis'          => $rangeEnd->format('Y-m'),
            'anomalien'    => $created->count(),
            'triggered_by' => $triggeredBy?->id,
        ]);

        return $created;
    }

    /**
     * Führt die eigentliche Prüfung für genau einen Monat durch (ohne Benachrichtigung).
     * Wird sowohl von runForEmployee() als auch von runForEmployeeRange() genutzt.
     */
    private function runMonthForEmployee(User $employe, Carbon $month, ?User $triggeredBy = null): Collection
    {
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd   = $month->copy()->endOfMonth();

        // Nur unquittierte, automatisch erzeugte Anomalien des Zeitraums entfernen (kein Verlust von HR-Entscheidungen)
        TimesheetAnomaly::forEmploye($employe->id)
            ->forPeriod($periodStart->month, $periodStart->year)
            ->unresolved()
            ->delete();

        $created = new Collection();

        foreach ($this->rules as $rule) {
            try {
                $results = $rule->check($employe, $periodStart, $periodEnd);
            } catch (\Throwable $e) {
                Log::error('TimeValidationService: Fehler in Prüfregel', [
                    'rule'    => get_class($rule),
                    'employe' => $employe->id,
                    'month'   => $periodStart->format('Y-m'),
                    'error'   => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($results as $result) {
                $anomaly = TimesheetAnomaly::create([
                    'employe_id'             => $employe->id,
                    'date'                   => $result['date'] ?? null,
                    'month'                  => $periodStart->month,
                    'year'                   => $periodStart->year,
                    'rule_type'              => $rule->ruleType()->value,
                    'severity'               => ($result['severity'] ?? $rule->ruleType()->defaultSeverity())->value,
                    'description'            => $result['description'],
                    'context'                => $result['context'] ?? null,
                    'related_employment_id'  => $result['related_employment_id'] ?? null,
                ]);
                $created->push($anomaly);
            }
        }

        // Timesheet-Metadaten aktualisieren (Monatssaldo neu berechnen lassen)
        $timesheet = Timesheet::where('employe_id', $employe->id)
            ->where('year', $periodStart->year)
            ->where('month', $periodStart->month)
            ->first();

        $timesheet?->updateTime();

        Log::info('Prüfengine: Zeiterfassungs-Prüflauf abgeschlossen', [
            'employe'      => $employe->id,
            'month'        => $periodStart->format('Y-m'),
            'anomalies'    => $created->count(),
            'triggered_by' => $triggeredBy?->id,
        ]);

        return $created;
    }

    /**
     * Arbeitspaket 5.2/5.3 (Erweiterung): Fordert Mitarbeiter auf, unvollständig
     * erfasste Arbeitszeiten an bereits vergangenen Dienstplantagen nachzutragen.
     * Die Dienstplanzeiten werden dabei als Vorschlag in der Nachricht genannt.
     * Höchstens eine Erinnerung pro Mitarbeiter und Tag (Throttling via Cache).
     */
    private function notifyEmployeeAboutMissingEntries(User $employe, Collection $anomalies): void
    {
        $today = Carbon::now();

        $openDays = $anomalies
            ->filter(fn (TimesheetAnomaly $a) => $a->rule_type === \App\Enums\AnomalyRuleType::MissingClockOut)
            ->filter(fn (TimesheetAnomaly $a) => $a->date !== null && $a->date->lessThanOrEqualTo($today))
            ->sortBy('date');

        if ($openDays->isEmpty()) {
            return;
        }

        $cacheKey = 'missing_timesheet_reminder_'.$employe->id.'_'.$today->format('Y-m-d');
        if (Cache::has($cacheKey)) {
            return;
        }

        $lines = $openDays->map(function (TimesheetAnomaly $a) {
            $context = $a->context ?? [];
            $suggestedStart = $context['suggested_start'] ?? null;
            $suggestedEnd   = $context['suggested_end'] ?? null;

            $line = $a->date->format('d.m.Y');
            if ($suggestedStart && $suggestedEnd) {
                $line .= sprintf(' (Vorschlag lt. Dienstplan: %s–%s Uhr)', $suggestedStart, $suggestedEnd);
            } elseif ($suggestedEnd) {
                $line .= sprintf(' (Vorschlag lt. Dienstplan: Ende %s Uhr)', $suggestedEnd);
            }

            return $line;
        });

        $title = 'Arbeitszeiten unvollständig';
        $body  = sprintf(
            "Bitte trage deine Arbeitszeiten für folgende Tage nach (Dienstplanzeiten werden vorgeschlagen):\n%s",
            $lines->implode("\n")
        );

        try {
            $employe->notify(new Push($title, $body));
            Cache::put($cacheKey, true, $today->copy()->endOfDay());
        } catch (\Throwable $e) {
            Log::warning('Prüfengine: Erinnerung an Mitarbeiter konnte nicht gesendet werden', [
                'employe' => $employe->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Führt den Prüflauf für alle sichtbaren Mitarbeiter einer Abteilung durch.
     */
    public function runForDepartment(\App\Models\Group $department, Carbon $month, ?User $triggeredBy = null): Collection
    {
        $employees = User::whereHas('employments', function ($q) use ($department) {
            $q->where('department_id', $department->id)->where('status', 'aktiv');
        })->get();

        $results = new Collection();
        foreach ($employees as $employe) {
            $results->put($employe->id, $this->runForEmployee($employe, $month, $triggeredBy));
        }

        return $results;
    }

    /**
     * Erweiterung: Prüflauf für eine ganze Abteilung über einen mehrmonatigen Zeitraum.
     */
    public function runForDepartmentRange(\App\Models\Group $department, Carbon $rangeStart, Carbon $rangeEnd, ?User $triggeredBy = null): Collection
    {
        $employees = User::whereHas('employments', function ($q) use ($department) {
            $q->where('department_id', $department->id)->where('status', 'aktiv');
        })->get();

        $results = new Collection();
        foreach ($employees as $employe) {
            $results->put($employe->id, $this->runForEmployeeRange($employe, $rangeStart, $rangeEnd, $triggeredBy));
        }

        return $results;
    }

    /**
     * Liefert alle Monatsanfänge (als Carbon) zwischen $rangeStart und $rangeEnd (inklusive).
     * Erlaubt Prüfläufe über beliebig lange Zeiträume (Quartal, Halbjahr, Jahr, ...).
     *
     * @return Carbon[]
     */
    private function monthsBetween(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $start = $rangeStart->copy()->startOfMonth();
        $end   = $rangeEnd->copy()->startOfMonth();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $months = [];
        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonth()) {
            $months[] = $cursor->copy();
        }

        return $months;
    }

    /**
     * Tagesgenaue Soll-Zeit-Ermittlung aus der Vertragshistorie (Arbeitspaket 3.3).
     * Liefert die Soll-Arbeitszeit in Sekunden für einen einzelnen Tag, basierend auf
     * den zu diesem Stichtag gültigen Anstellungen (Employment.start/end = valid_from/valid_to).
     */
    public function dailyTargetSeconds(User $employe, Carbon $date): float
    {
        $percent = $employe->employments_date($date)->sum('percent');

        return percent_to_seconds($percent) / 5; // 5 Arbeitstage/Woche, analog Timesheet::updateTime()
    }

    /**
     * Monatssaldo gemäß Konzept-Formel:
     * Monatssaldo = Σ Ist-Arbeitszeit + Σ Urlaubs-/Krank-Gutschrift − Σ Tages-Sollzeit (lt. Vertragshistorie)
     *
     * @return array{ist_seconds: float, soll_seconds: float, credit_seconds: float, balance_seconds: float}
     */
    public function calculateMonthlyBalance(User $employe, Carbon $month): array
    {
        return $this->calculateBalance($employe, $month->copy()->startOfMonth(), $month->copy()->endOfMonth());
    }

    /**
     * Erweiterung: Saldo-Berechnung über einen beliebig langen Zeitraum (mehrere Monate),
     * gemäß derselben Konzept-Formel wie calculateMonthlyBalance(). Berücksichtigt alle
     * Timesheets, deren Monat innerhalb des Zeitraums liegt.
     *
     * @return array{ist_seconds: float, soll_seconds: float, credit_seconds: float, balance_seconds: float}
     */
    public function calculateBalance(User $employe, Carbon $periodStart, Carbon $periodEnd): array
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd   = $periodEnd->copy()->endOfDay();

        $timesheetDays = new Collection();
        foreach ($this->monthsBetween($periodStart, $periodEnd) as $month) {
            $timesheet = Timesheet::where('employe_id', $employe->id)
                ->where('year', $month->year)
                ->where('month', $month->month)
                ->first();

            if ($timesheet !== null) {
                $timesheetDays = $timesheetDays->merge($timesheet->timesheet_days);
            }
        }

        $istSeconds = 0.0;
        $creditSeconds = 0.0;
        $sollSeconds = 0.0;

        for ($day = $periodStart->copy(); $day->lessThanOrEqualTo($periodEnd); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $entries = $timesheetDays->filter(fn ($d) => $d->date->format('Y-m-d') === $key);

            $worked = $entries->filter(fn ($d) => $d->start !== null && $d->end !== null)->sum('duration');
            $credit = $entries->filter(fn ($d) => $d->percent_of_workingtime !== null)->sum('duration');

            $istSeconds += max($worked, 0);
            $creditSeconds += max($credit, 0);

            if ($day->isWeekday() && !is_holiday($day)) {
                $sollSeconds += $this->dailyTargetSeconds($employe, $day);
            }
        }

        return [
            'ist_seconds'     => $istSeconds,
            'credit_seconds'  => $creditSeconds,
            'soll_seconds'    => $sollSeconds,
            'balance_seconds' => $istSeconds + $creditSeconds - $sollSeconds,
        ];
    }
}








