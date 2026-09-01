<?php

namespace App\Services\Personal\Validation\Rules;

use App\Enums\AnomalyRuleType;
use App\Enums\AnomalySeverity;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use App\Services\Personal\Validation\ValidationRuleInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ROSTER_DEVIATION: Vergleicht die Ist-Arbeitszeit mit dem Dienstplan (WorkingTime).
 * Schlägt an, wenn die Abweichung den konfigurierten Schwellenwert (Default 30 Min.)
 * überschreitet. Severity: WARNING.
 */
class RosterDeviationRule implements ValidationRuleInterface
{
    public function ruleType(): AnomalyRuleType
    {
        return AnomalyRuleType::RosterDeviation;
    }

    public function check(User $employe, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $anomalies = new Collection();
        $thresholdMinutes = (int) config('timesheet_validation.roster_deviation_threshold_minutes', 30);

        $workingTimes = $employe->working_times()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->groupBy(fn ($w) => $w->date->format('Y-m-d'));

        $timesheetDays = TimesheetDays::whereHas('timesheet', function ($q) use ($employe) {
                $q->where('employe_id', $employe->id);
            })
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->groupBy(fn ($d) => $d->date->format('Y-m-d'));

        foreach ($workingTimes as $date => $planned) {
            $sollMinutes = $planned->sum(fn ($w) => $w->duration ?? 0);
            $istEntries = $timesheetDays->get($date, new Collection());
            $istMinutes = $istEntries->sum(fn ($d) => ($d->duration ?? 0) / 60);

            $deviation = abs($istMinutes - $sollMinutes);

            if ($deviation > $thresholdMinutes) {
                $anomalies->push([
                    'date'        => $date,
                    'severity'    => AnomalySeverity::Warning,
                    'description' => sprintf(
                        'Dienstplan-Abweichung am %s: Soll %s Min., Ist %s Min. (Differenz %s Min.).',
                        Carbon::parse($date)->format('d.m.Y'),
                        round($sollMinutes), round($istMinutes), round($deviation)
                    ),
                    'context' => [
                        'soll_minutes' => round($sollMinutes),
                        'ist_minutes'  => round($istMinutes),
                        'threshold'    => $thresholdMinutes,
                    ],
                ]);
            }
        }

        return $anomalies;
    }
}

