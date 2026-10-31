<?php

namespace App\Http\Controllers\Traits;

use App\Models\PaedDiaryColumn;
use Illuminate\Support\Str;

/**
 * Gemeinsame Hilfsmethoden für alle PaedDiary-Controller.
 */
trait PaedDiaryHelperTrait
{
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

