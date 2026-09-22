<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LessonTime extends Model
{
    protected $fillable = ['period', 'start', 'end', 'week', 'zeitraster_id'];

    /**
     * Relation: gehört zu einem Zeitraster (nullable = globaler Eintrag)
     */
    public function zeitraster()
    {
        return $this->belongsTo(Zeitraster::class, 'zeitraster_id');
    }

    /**
     * Löst eine Stundennummer in Start- und Endzeit auf.
     *
     * Fallback-Kette (von spezifisch nach allgemein):
     *   1. (period, week, zeitrasterId)   – exakter Treffer
     *   2. (period, week, null)           – week-spezifisch, globales Zeitraster
     *   3. (period, null, zeitrasterId)   – zeitraster-spezifisch, keine Woche
     *   4. (period, null, null)           – vollständiger globaler Fallback
     *
     * Duplikate (z. B. wenn week=null oder zeitrasterId=null) werden übersprungen.
     */
    public static function resolveTime(int $period, ?string $week = null, ?int $zeitrasterId = null): ?array
    {
        $chain = [
            ['week' => $week, 'zeitraster_id' => $zeitrasterId],
            ['week' => $week, 'zeitraster_id' => null],
            ['week' => null,  'zeitraster_id' => $zeitrasterId],
            ['week' => null,  'zeitraster_id' => null],
        ];

        $seen = [];
        foreach ($chain as $cand) {
            $key = serialize($cand);
            if (in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $q = static::where('period', $period);
            $cand['week'] === null
                ? $q->whereNull('week')
                : $q->where('week', $cand['week']);
            $cand['zeitraster_id'] === null
                ? $q->whereNull('zeitraster_id')
                : $q->where('zeitraster_id', $cand['zeitraster_id']);

            $lt = $q->first();
            if ($lt) {
                return ['start' => $lt->start, 'end' => $lt->end];
            }
        }

        Log::warning("LessonTime: Keine Zeitangabe für Stunde {$period} (Woche: {$week}) gefunden.");
        return null;
    }

    /**
     * Löst eine Stundennummer mit optionaler Doppelstunden-Auflösung auf.
     * Bei $count > 1 wird die Endzeit der letzten Stunde verwendet.
     */
    public static function resolveTimeRange(int $period, int $count = 1, ?string $week = null, ?int $zeitrasterId = null): ?array
    {
        $start = static::resolveTime($period, $week, $zeitrasterId);
        if (!$start) {
            return null;
        }

        if ($count <= 1) {
            return $start;
        }

        $endPeriod = static::resolveTime($period + $count - 1, $week, $zeitrasterId);
        if (!$endPeriod) {
            // Fallback: nur die erste Stunde
            return $start;
        }

        return [
            'start' => $start['start'],
            'end'   => $endPeriod['end'],
        ];
    }
}
