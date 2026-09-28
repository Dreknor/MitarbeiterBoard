<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kompakt-Status eines Schülers für die Klassenliste.
 *
 * @mixin \App\Models\Schueler
 */
class ClassStudentResource extends JsonResource
{
    public function toArray($request): array
    {
        $stage = $this->relationLoaded('grading_stage') ? $this->grading_stage : null;

        return [
            'id' => $this->id,
            'firstname' => $this->vorname,
            'lastname' => $this->nachname,
            'current_grading' => $stage ? [
                'stage_id' => $stage->id,
                'stage_title' => $stage->name,
                'symbol' => $stage->symbol,
                'badge_url' => $stage->image_url,
            ] : null,
            'active_diagnostic_goals_count' => $this->when(
                isset($this->active_diagnostic_goals_count),
                fn () => (int) $this->active_diagnostic_goals_count
            ),
            'recent_diary_entries_count' => (int) ($this->recent_diary_entries_count ?? 0),
        ];
    }
}
