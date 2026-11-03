<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DiagnosticSession */
class DiagnosticSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'schueler_id' => $this->schueler_id,
            'area_id' => $this->diagnostic_area_id,
            'area_title' => $this->area?->name,
            'session_date' => $this->session_date?->toDateString(),
            'is_completed' => (bool) $this->is_completed,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_by_name' => $this->user?->name,
            'stage_notes' => $this->whenLoaded('stageNotes', fn () => $this->stageNotes->map(fn ($n) => [
                'stage_id' => $n->diagnostic_stage_id,
                'stage_title' => $n->stage?->name,
                'notes' => $n->notes,
            ])->values()),
            'assessments' => $this->whenLoaded('assessments', fn () => $this->assessments->map(fn ($a) => [
                'criterion_id' => $a->diagnostic_goal_id,
                'rating' => $a->rating,
                'is_current_goal' => (bool) $a->is_current_goal,
            ])->values()),
            'rating_summary' => $this->whenLoaded('assessments', fn () => [
                'white' => $this->assessments->where('rating', 'white')->count(),
                'gray' => $this->assessments->where('rating', 'gray')->count(),
                'dark_gray' => $this->assessments->where('rating', 'dark_gray')->count(),
                'total' => $this->assessments->count(),
            ]),
            'development_goals' => DiagnosticGoalResource::collection($this->whenLoaded('developmentGoals')),
        ];
    }
}
