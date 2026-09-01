<?php

namespace App\Services\Personal\Validation\Rules;

use App\Enums\AnomalyRuleType;
use App\Enums\AnomalySeverity;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use App\Services\Personal\Validation\ValidationRuleInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * VACATION_CONFLICT: Prüft, ob an genehmigten Urlaubstagen Arbeitszeiten gebucht
 * wurden oder ob an Urlaubstagen die Soll-Gutschrift fehlt. Severity: HIGH.
 */
class VacationConflictRule implements ValidationRuleInterface
{
    public function ruleType(): AnomalyRuleType
    {
        return AnomalyRuleType::VacationConflict;
    }

    public function check(User $employe, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $anomalies = new Collection();

        $holidays = $employe->holidays()
            ->where('approved', true)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('end_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhere(function ($qq) use ($periodStart, $periodEnd) {
                      $qq->where('start_date', '<=', $periodStart->toDateString())
                         ->where('end_date', '>=', $periodEnd->toDateString());
                  });
            })
            ->get();

        if ($holidays->isEmpty()) {
            return $anomalies;
        }

        $timesheetDays = TimesheetDays::whereHas('timesheet', function ($q) use ($employe) {
                $q->where('employe_id', $employe->id);
            })
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->groupBy(fn ($d) => $d->date->format('Y-m-d'));

        foreach ($holidays as $holiday) {
            for ($day = $holiday->start_date->copy(); $day->lessThanOrEqualTo($holiday->end_date); $day->addDay()) {
                if ($day->lessThan($periodStart) || $day->greaterThan($periodEnd)) {
                    continue;
                }
                if (!$day->isWeekday() || is_holiday($day)) {
                    continue;
                }

                $key = $day->format('Y-m-d');
                $entries = $timesheetDays->get($key, new Collection());

                $hasWorkedTime = $entries->contains(fn ($e) => $e->start !== null && $e->end !== null);
                $hasCredit = $entries->contains(fn ($e) => Str::contains(strtolower((string) $e->comment), 'urlaub'));

                if ($hasWorkedTime) {
                    $anomalies->push([
                        'date'        => $key,
                        'severity'    => AnomalySeverity::High,
                        'description' => sprintf('Arbeitszeit am genehmigten Urlaubstag %s gebucht.', $day->format('d.m.Y')),
                        'context'     => ['holiday_id' => $holiday->id],
                    ]);
                } elseif (!$hasCredit) {
                    $anomalies->push([
                        'date'        => $key,
                        'severity'    => AnomalySeverity::High,
                        'description' => sprintf('Fehlende Urlaubs-Gutschrift am genehmigten Urlaubstag %s.', $day->format('d.m.Y')),
                        'context'     => ['holiday_id' => $holiday->id],
                    ]);
                }
            }
        }

        return $anomalies;
    }
}

