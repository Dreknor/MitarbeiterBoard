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
 * OVERLAP_CHECK: Prüft, ob sich zwei oder mehr erfasste Zeitintervalle
 * einer Person am selben Tag zeitlich überschneiden. Severity: CRITICAL.
 */
class OverlapCheckRule implements ValidationRuleInterface
{
    public function ruleType(): AnomalyRuleType
    {
        return AnomalyRuleType::OverlapCheck;
    }

    public function check(User $employe, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $anomalies = new Collection();

        $days = TimesheetDays::whereHas('timesheet', function ($q) use ($employe) {
                $q->where('employe_id', $employe->id);
            })
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereNotNull('start')
            ->whereNotNull('end')
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($d) => $d->date->format('Y-m-d'));

        foreach ($days as $date => $entries) {
            $entries = $entries->values();
            for ($i = 0; $i < $entries->count(); $i++) {
                for ($j = $i + 1; $j < $entries->count(); $j++) {
                    $a = $entries[$i];
                    $b = $entries[$j];

                    if ($a->start->lessThan($b->end) && $b->start->lessThan($a->end)) {
                        $anomalies->push([
                            'date'        => $date,
                            'severity'    => AnomalySeverity::Critical,
                            'description' => sprintf(
                                'Überschneidende Zeitbuchungen am %s: %s–%s und %s–%s.',
                                Carbon::parse($date)->format('d.m.Y'),
                                $a->start->format('H:i'), $a->end->format('H:i'),
                                $b->start->format('H:i'), $b->end->format('H:i')
                            ),
                            'context' => [
                                'timesheet_day_ids' => [$a->id, $b->id],
                            ],
                        ]);
                    }
                }
            }
        }

        return $anomalies;
    }
}

