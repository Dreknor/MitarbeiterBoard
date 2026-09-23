<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Als "aktuelles Ziel" markiertes Katalogkriterium (DiagnosticAssessment mit is_current_goal),
 * wie es im Web-Frontend der Diagnosebögen gesetzt wird.
 *
 * @mixin \App\Models\DiagnosticAssessment
 */
class CurrentCriterionGoalResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'assessment_id' => $this->id,
            'criterion_id' => $this->diagnostic_goal_id,
            'code' => $this->goal?->code,
            'description' => $this->goal?->description,
            'stage_title' => $this->goal?->stage?->name,
            'area_id' => $this->goal?->stage?->diagnostic_area_id,
            'area_title' => $this->goal?->stage?->area?->name,
            'rating' => $this->rating,
            'session_id' => $this->diagnostic_session_id,
            'session_date' => $this->session?->session_date?->toDateString(),
        ];
    }
}
