<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Individuelles Entwicklungsziel eines Schülers.
 *
 * @mixin \App\Models\DiagnosticDevelopmentGoal
 */
class DiagnosticGoalResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'schueler_id' => $this->schueler_id,
            'diagnostic_session_id' => $this->diagnostic_session_id,
            'area_id' => $this->diagnostic_area_id,
            'area_title' => $this->area?->name,
            'stage_id' => $this->diagnostic_stage_id,
            'criterion_id' => $this->diagnostic_goal_id,
            'title' => $this->title,
            'target_date' => $this->target_date?->toDateString(),
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'completion_notes' => $this->completion_notes,
            'completed_at' => $this->completed_at?->toDateString(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
