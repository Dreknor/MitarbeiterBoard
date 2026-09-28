<?php

namespace App\Http\Resources\API\v1;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Klasse */
class ClassResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->kuerzel,
            'color' => $this->color,
            'school_year' => self::currentSchoolYear(),
            'grading_system_id' => $this->grading_system_id,
            'students_count' => (int) ($this->students_count ?? 0),
        ];
    }

    /**
     * Aktuelles Schuljahr im Format "2026/2027" (Beginn laut config.schuljahresbeginn).
     */
    public static function currentSchoolYear(): string
    {
        $start = config('config.schuljahresbeginn');
        $start = $start instanceof Carbon ? $start : Carbon::parse($start ?: now()->startOfYear());

        return $start->year . '/' . ($start->year + 1);
    }
}
