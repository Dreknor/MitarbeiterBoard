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
        // Pro Request nur einmal lesen (wird in der Wochenansicht für alle Schüler benötigt)
        static $cache = null;
        if ($cache !== null && !app()->runningUnitTests()) {
            return $cache;
        }

        return $cache = [
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
        return static::patternSummariesForStudents([$schuelerId], $from, $to)[$schuelerId] ?? [];
    }

    /**
     * Fehlzeitenmuster für mehrere Schüler mit einer Abfrage (Wochenansicht).
     *
     * @param int[] $schuelerIds
     * @return array<int, array> [schueler_id => alerts]
     */
    public static function patternSummariesForStudents(array $schuelerIds, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if (empty($schuelerIds)) {
            return [];
        }

        $end = $to?->copy()->endOfDay() ?? Carbon::today()->endOfDay();
        $start = $from?->copy()->startOfDay() ?? $end->copy()->subDays(42)->startOfDay();
        $settings = static::patternSettings();

        $grouped = static::whereIn('schueler_id', $schuelerIds)
            ->whereBetween('datum', [$start->toDateString(), $end->toDateString()])
            ->orderBy('datum')
            ->get(['schueler_id', 'datum'])
            ->groupBy('schueler_id');

        $out = [];
        foreach ($grouped as $schuelerId => $entries) {
            $dates = $entries->map(fn ($entry) => Carbon::parse($entry->datum)->startOfDay())->values()->all();
            $alerts = static::alertsForDates($dates, $start, $end, $settings);
            if ($alerts) {
                $out[(int) $schuelerId] = $alerts;
            }
        }

        return $out;
    }

    /**
     * Wertet die Regeln aus. Jede Regel gilt für ein gleitendes Fenster (Einstellungen "…window_days")
     * innerhalb des Zeitraums – nicht für den ganzen Zeitraum, sonst wäre über ein Schuljahr fast
     * jedes Kind auffällig.
     *
     * @param Carbon[] $dates aufsteigend sortiert
     */
    protected static function alertsForDates(array $dates, Carbon $start, Carbon $end, array $settings): array
    {
        if (empty($dates)) {
            return [];
        }

        $alerts = [];

        // Gesamtfehltage im ungünstigsten Fenster
        $totalWindow = max(1, (int) $settings['total_absence_window_days']);
        [$maxCount, $windowDates] = static::maxInWindow($dates, $totalWindow);
        $windowEnd = $windowDates[count($windowDates) - 1]->copy()->min($end);
        $windowStart = $windowEnd->copy()->subDays($totalWindow - 1)->max($start);
        $schoolDays = static::countSchoolDays($windowStart, $windowEnd);
        $thresholdDays = (int) $settings['total_absence_days_threshold'];
        $thresholdPercent = (float) $settings['total_absence_percent_threshold'];
        $percent = $schoolDays > 0 ? round(($maxCount / $schoolDays) * 100, 1) : 0;

        if ($maxCount >= $thresholdDays || ($schoolDays > 0 && $percent >= $thresholdPercent)) {
            $alerts[] = [
                'type' => 'total_absence',
                'label' => 'Gesamtfehltage',
                'severity' => 'warning',
                'summary' => $maxCount . ' Fehltage innerhalb von ' . $totalWindow . ' Tagen.',
                'details' => 'Schwelle: ≥ ' . $thresholdDays . ' Fehltage oder ≥ ' . $thresholdPercent . ' % der Schultage innerhalb von ' . $totalWindow . ' Tagen. Höchstwert: ' . $percent . ' % (' . $schoolDays . ' Schultage, ' . $windowStart->format('d.m.') . '–' . $windowEnd->format('d.m.Y') . ').',
                'count' => $maxCount,
                'dates' => array_map(fn (Carbon $date) => $date->toDateString(), $windowDates),
            ];
        }

        // Mehrere kurze Abwesenheiten (1–n Tage) innerhalb des Fensters
        $shortClusters = static::collectShortClusters($dates, (int) $settings['short_cluster_max_days']);
        $minShortClusters = (int) $settings['short_cluster_count'];
        $clusterWindow = max(1, (int) $settings['short_cluster_window_days']);
        [$clusterCount, $clusterStarts] = static::maxInWindow(array_map(fn ($cluster) => $cluster[0], $shortClusters), $clusterWindow);
        if ($clusterCount >= $minShortClusters) {
            $starts = array_map(fn (Carbon $d) => $d->toDateString(), $clusterStarts);
            $inWindow = array_values(array_filter($shortClusters, fn ($cluster) => in_array($cluster[0]->toDateString(), $starts, true)));
            $alerts[] = [
                'type' => 'short_cluster',
                'label' => 'Kurzzeit-Häufung',
                'severity' => 'warning',
                'summary' => $clusterCount . ' kurze Abwesenheiten innerhalb von ' . $clusterWindow . ' Tagen.',
                'details' => 'Schwelle: mindestens ' . $minShortClusters . ' getrennte Abwesenheiten (1–' . (int) $settings['short_cluster_max_days'] . ' Tage) innerhalb von ' . $clusterWindow . ' Tagen.',
                'count' => $clusterCount,
                'dates' => array_values(array_unique(array_merge(...array_map(fn ($cluster) => array_map(fn ($date) => $date->toDateString(), $cluster), $inWindow)))),
            ];
        }

        $weekdayPattern = static::findWeekdayPattern($dates, $settings);
        if ($weekdayPattern) {
            $alerts[] = $weekdayPattern;
        }

        return $alerts;
    }

    /**
     * Höchste Anzahl an Daten innerhalb eines gleitenden Fensters von $windowDays Tagen.
     *
     * @param Carbon[] $dates aufsteigend sortiert
     * @return array{0: int, 1: Carbon[]} [Anzahl, Daten im ungünstigsten Fenster]
     */
    protected static function maxInWindow(array $dates, int $windowDays): array
    {
        $best = [];
        $left = 0;
        foreach ($dates as $right => $date) {
            while ($dates[$left]->diffInDays($date) >= $windowDays) {
                $left++;
            }
            if ($right - $left + 1 > count($best)) {
                $best = array_slice($dates, $left, $right - $left + 1);
            }
        }

        return [count($best), $best];
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

        $weekdayDates = [0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];
        foreach ($dates as $date) {
            $weekdayDates[$date->dayOfWeek][] = $date;
        }

        $threshold = (int) $settings['weekday_threshold'];
        $window = max(1, (int) $settings['weekday_window_days']);
        $plural = ['Sonntagen', 'Montagen', 'Dienstagen', 'Mittwochen', 'Donnerstagen', 'Freitagen', 'Samstagen'];

        foreach ($weekdayDates as $weekday => $list) {
            [$count, $inWindow] = static::maxInWindow($list, $window);
            if ($count >= $threshold) {
                return [
                    'type' => 'weekday_pattern',
                    'label' => 'Wochentagsmuster',
                    'severity' => 'warning',
                    'summary' => 'Auffällige Häufung an ' . $plural[$weekday] . '.',
                    'details' => 'An ' . $count . ' ' . $plural[$weekday] . ' innerhalb von ' . $window . ' Tagen abwesend. Schwelle: ≥ ' . $threshold . '.',
                    'count' => $count,
                    'dates' => array_map(fn (Carbon $date) => $date->toDateString(), $inWindow),
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

