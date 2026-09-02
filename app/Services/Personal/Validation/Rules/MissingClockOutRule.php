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
 * MISSING_CLOCK_OUT: Identifiziert unvollständige Zeitbuchungen (fehlender
 * Stempel-Ausstieg) sowie unentschuldigtes Fehlen an geplanten Dienstplantagen.
 * Severity: HIGH.
 */
class MissingClockOutRule implements ValidationRuleInterface
{
    public function ruleType(): AnomalyRuleType
    {
        return AnomalyRuleType::MissingClockOut;
    }

    public function check(User $employe, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $anomalies = new Collection();

        $workingTimesByDay = $employe->working_times()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->groupBy(fn ($w) => $w->date->format('Y-m-d'));

        // 1) Unvollständige Zeitbuchungen: Start vorhanden, Ende fehlt.
        $incomplete = TimesheetDays::whereHas('timesheet', function ($q) use ($employe) {
                $q->where('employe_id', $employe->id);
            })
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereNotNull('start')
            ->whereNull('end')
            ->get();

        foreach ($incomplete as $entry) {
            $key = $entry->date->format('Y-m-d');
            $suggestion = $this->rosterSuggestion($workingTimesByDay->get($key, new Collection()));

            $description = sprintf('Fehlender Stempel-Ausstieg am %s (Start %s Uhr).', $entry->date->format('d.m.Y'), $entry->start->format('H:i'));
            if ($suggestion['end'] !== null) {
                $description .= sprintf(' Dienstplan schlägt %s Uhr als Ende vor.', $suggestion['end']);
            }

            $anomalies->push([
                'date'        => $key,
                'severity'    => AnomalySeverity::High,
                'description' => $description,
                'context'     => [
                    'timesheet_day_id' => $entry->id,
                    'suggested_start'  => $entry->start->format('H:i'),
                    'suggested_end'    => $suggestion['end'],
                ],
            ]);
        }

        // 2) geplanter Dienst ohne Zeitbuchung, Urlaub oder Abwesenheit.
        if ($workingTimesByDay->isEmpty()) {
            return $anomalies;
        }

        $bookedDays = TimesheetDays::whereHas('timesheet', function ($q) use ($employe) {
                $q->where('employe_id', $employe->id);
            })
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique();

        $holidayDays = $employe->holidays()->where('approved', true)->get();
        $absences = $employe->absences ?? collect();

        foreach ($workingTimesByDay as $date => $planned) {
            $day = Carbon::parse($date);

            if ($bookedDays->contains($date)) {
                continue;
            }

            $onHoliday = $holidayDays->contains(fn ($h) => $day->betweenIncluded($h->start_date, $h->end_date));
            $onAbsence = method_exists($absences, 'contains')
                ? $absences->contains(fn ($a) => $day->betweenIncluded($a->start, $a->end))
                : false;

            if (!$onHoliday && !$onAbsence) {
                $suggestion = $this->rosterSuggestion($planned);

                $description = sprintf('Keine Zeitbuchung am geplanten Dienstplantag %s.', $day->format('d.m.Y'));
                if ($suggestion['start'] !== null && $suggestion['end'] !== null) {
                    $description .= sprintf(' Dienstplan schlägt %s–%s Uhr vor.', $suggestion['start'], $suggestion['end']);
                }

                $anomalies->push([
                    'date'        => $date,
                    'severity'    => AnomalySeverity::High,
                    'description' => $description,
                    'context'     => [
                        'working_time_ids' => $planned->pluck('id'),
                        'suggested_start'   => $suggestion['start'],
                        'suggested_end'     => $suggestion['end'],
                    ],
                ]);
            }
        }

        return $anomalies;
    }

    /**
     * Ermittelt aus den Dienstplan-Einträgen (WorkingTime) eines Tages einen
     * Vorschlag für Beginn/Ende der Zeitbuchung (frühester Beginn, spätestes Ende).
     *
     * @return array{start: ?string, end: ?string}
     */
    private function rosterSuggestion(Collection $workingTimes): array
    {
        $withTimes = $workingTimes->filter(fn ($w) => $w->start !== null && $w->end !== null);

        if ($withTimes->isEmpty()) {
            return ['start' => null, 'end' => null];
        }

        return [
            'start' => $withTimes->min(fn ($w) => $w->start)->format('H:i'),
            'end'   => $withTimes->max(fn ($w) => $w->end)->format('H:i'),
        ];
    }
}


