<?php

namespace App\Http\Controllers\Traits;

use App\Models\PaedDiaryColumn;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gemeinsame Hilfsmethoden für alle PaedDiary-Controller.
 */
trait PaedDiaryHelperTrait
{
    private function weekCacheKey(int|string $klasseId, Carbon $weekStart): string
    {
        return "paed_diary_week_{$klasseId}_{$weekStart->startOfWeek()->toDateString()}";
    }

    private function forgetWeekCache(int|string $klasseId, Carbon $date): void
    {
        Cache::forget($this->weekCacheKey($klasseId, $date->copy()->startOfWeek()));
    }

    private function generateUniqueSlug(string $baseSlug, int $klasseId): string
    {
        $slug    = $baseSlug;
        $counter = 2;
        while (PaedDiaryColumn::where('klasse_id', $klasseId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
}

