<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaedDiarySchuelerAbsence extends Model
{
    use HasFactory;

    protected $table = 'paed_diary_schueler_absences';

    protected $fillable = ['schueler_id', 'klasse_id', 'datum', 'marked_by'];

    protected $casts = ['datum' => 'date'];

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }

    public function klasse()
    {
        return $this->belongsTo(Klasse::class);
    }

    public function markedByUser()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public static function patternSettings(): array
    {
        return [
            'total_absence_days_threshold' => (int) (settings('paed_diary_absence_pattern_total_absence_days_threshold') ?? config('config.paed_diary_absence_pattern_total_absence_days_threshold', 10)),
            'total_absence_percent_threshold' => (float) (settings('paed_diary_absence_pattern_total_absence_percent_threshold') ?? config('config.paed_diary_absence_pattern_total_absence_percent_threshold', 10)),
            'total_absence_window_days' => (int) (settings('paed_diary_absence_pattern_total_absence_window_days') ?? config('config.paed_diary_absence_pattern_total_absence_window_days', 28)),
            'short_cluster_count' => (int) (settings('paed_diary_absence_pattern_short_cluster_count') ?? config('config.paed_diary_absence_pattern_short_cluster_count', 3)),
            'short_cluster_max_days' => (int) (settings('paed_diary_absence_pattern_short_cluster_max_days') ?? config('config.paed_diary_absence_pattern_short_cluster_max_days', 2)),
            'short_cluster_window_days' => (int) (settings('paed_diary_absence_pattern_short_cluster_window_days') ?? config('config.paed_diary_absence_pattern_short_cluster_window_days', 30)),
            'weekday_threshold' => (int) (settings('paed_diary_absence_pattern_weekday_threshold') ?? config('config.paed_diary_absence_pattern_weekday_threshold', 3)),
            'weekday_window_days' => (int) (settings('paed_diary_absence_pattern_weekday_window_days') ?? config('config.paed_diary_absence_pattern_weekday_window_days', 42)),
        ];
    }

    public static function patternSummaryForStudent(int $schuelerId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $end = $to?->copy()->endOfDay() ?? Carbon::today()->endOfDay();
        $start = $from?->copy()->startOfDay() ?? $end->copy()->subDays(42)->startOfDay();

        $entries = static::where('schueler_id', $schuelerId)
            ->whereBetween('datum', [$start->toDateString(), $end->toDateString()])
            ->orderBy('datum')
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $dates = $entries->map(fn ($entry) => Carbon::parse($entry->datum)->startOfDay())
            ->sortBy(fn (Carbon $date) => $date->timestamp)
            ->values()
            ->all();

        $settings = static::patternSettings();
        $alerts = [];

        $totalAbsences = count($dates);
        $schoolDays = static::countSchoolDays($start, $end);
        $thresholdDays = (int) ($settings['total_absence_days_threshold'] ?? 10);
        $thresholdPercent = (float) ($settings['total_absence_percent_threshold'] ?? 10.0);

        if ($totalAbsences >= $thresholdDays || ($schoolDays > 0 && (($totalAbsences / $schoolDays) * 100) >= $thresholdPercent)) {
            $percent = $schoolDays > 0 ? round(($totalAbsences / $schoolDays) * 100, 1) : 0;
            $alerts[] = [
                'type' => 'total_absence',
                'label' => 'Gesamtfehltage',
                'severity' => 'warning',
                'summary' => 'Im gewählten Zeitraum wurden ' . $totalAbsences . ' Fehltage registriert.',
                'details' => 'Schwelle: ≥ ' . $thresholdDays . ' Fehltage oder ≥ ' . $thresholdPercent . '% der schulischen Tage (' . $schoolDays . ' Tage). Aktuell: ' . $percent . '%.',
                'count' => $totalAbsences,
                'dates' => array_map(fn (Carbon $date) => $date->toDateString(), $dates),
            ];
        }

        $shortClusters = static::collectShortClusters($dates, (int) ($settings['short_cluster_max_days'] ?? 2));
        $minShortClusters = (int) ($settings['short_cluster_count'] ?? 3);
        if (count($shortClusters) >= $minShortClusters) {
            $alerts[] = [
                'type' => 'short_cluster',
                'label' => 'Kurzzeit-Häufung',
                'severity' => 'warning',
                'summary' => 'Mehrere kurze Abwesenheiten häufen sich in kurzer Zeit.',
                'details' => 'Mindestens ' . $minShortClusters . ' getrennte Krankmeldungen (1–2 Tage) innerhalb von ' . ($settings['short_cluster_window_days'] ?? 30) . ' Tagen wurden erreicht.',
                'count' => count($shortClusters),
                'dates' => array_values(array_unique(array_merge(...array_map(fn ($cluster) => array_map(fn ($date) => $date->toDateString(), $cluster), $shortClusters)))),
            ];
        }

        $weekdayPattern = static::findWeekdayPattern($dates, $settings);
        if ($weekdayPattern) {
            $alerts[] = $weekdayPattern;
        }

        return $alerts;
    }

    protected static function collectShortClusters(array $dates, int $maxDaysPerCluster): array
    {
        if (empty($dates)) {
            return [];
        }

        $clusters = [];
        $currentCluster = [];
        $previousDate = null;

        foreach ($dates as $date) {
            if ($previousDate && $date->diffInDays($previousDate) > 1) {
                if (!empty($currentCluster)) {
                    $clusters[] = $currentCluster;
                }
                $currentCluster = [$date];
            } else {
                $currentCluster[] = $date;
            }
            $previousDate = $date;
        }

        if (!empty($currentCluster)) {
            $clusters[] = $currentCluster;
        }

        return array_values(array_filter($clusters, fn ($cluster) => count($cluster) >= 1 && count($cluster) <= $maxDaysPerCluster));
    }

    protected static function findWeekdayPattern(array $dates, array $settings): ?array
    {
        if (empty($dates)) {
            return null;
        }

        $weekdayCounts = [
            0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [],
        ];

        foreach ($dates as $date) {
            $weekdayCounts[$date->dayOfWeek][] = $date;
        }

        $threshold = (int) ($settings['weekday_threshold'] ?? 3);
        foreach ($weekdayCounts as $weekday => $weekdayDates) {
            if (count($weekdayDates) >= $threshold) {
                $name = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][$weekday];
                $datesStr = array_map(fn (Carbon $date) => $date->toDateString(), $weekdayDates);

                return [
                    'type' => 'weekday_pattern',
                    'label' => 'Wochentagsmuster',
                    'severity' => 'warning',
                    'summary' => 'Auffällige Häufung an ' . $name . 'n.',
                    'details' => 'An ' . $name . ' wurden ' . count($weekdayDates) . ' Abwesenheiten vermerkt. Schwelle: ≥ ' . $threshold . ' an einem Wochentag innerhalb von ' . ($settings['weekday_window_days'] ?? 42) . ' Tagen.',
                    'count' => count($weekdayDates),
                    'dates' => $datesStr,
                ];
            }
        }

        return null;
    }

    protected static function countSchoolDays(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday() && !is_holiday($current) && !is_ferien($current)) {
                $count++;
            }

            $current->addDay();
        }

        return $count;
    }

    /**
     * Scope: Alle Abwesenheiten für eine Klasse in einem Zeitraum.
     */
    public function scopeForKlasseInRange($query, int $klasseId, string $from, string $to)
    {
        return $query->where('klasse_id', $klasseId)
                     ->whereBetween('datum', [$from, $to]);
    }
}

