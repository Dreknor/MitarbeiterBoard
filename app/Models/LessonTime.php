<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LessonTime extends Model
{
    protected $fillable = ['period', 'start', 'end', 'week'];

    /**
     * Löst eine Stundennummer in Start- und Endzeit auf.
     * Spezifische Woche (A/B) hat Vorrang vor generischem Eintrag (week=null).
     */
    public static function resolveTime(int $period, ?string $week = null): ?array
    {
        $lessonTime = static::where('period', $period)
            ->where(function ($q) use ($week) {
                $q->whereNull('week');
                if ($week) {
                    $q->orWhere('week', $week);
                }
            })
            ->orderByRaw('CASE WHEN week IS NULL THEN 1 ELSE 0 END') // spezifische Woche zuerst
            ->first();

        if (!$lessonTime) {
            Log::warning("LessonTime: Keine Zeitangabe für Stunde {$period} (Woche: {$week}) gefunden.");
            return null;
        }

        return [
            'start' => $lessonTime->start,
            'end'   => $lessonTime->end,
        ];
    }

    /**
     * Löst eine Stundennummer mit optionaler Doppelstunden-Auflösung auf.
     * Bei $count > 1 wird die Endzeit der letzten Stunde verwendet.
     */
    public static function resolveTimeRange(int $period, int $count = 1, ?string $week = null): ?array
    {
        $start = static::resolveTime($period, $week);
        if (!$start) {
            return null;
        }

        if ($count <= 1) {
            return $start;
        }

        $endPeriod = static::resolveTime($period + $count - 1, $week);
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

