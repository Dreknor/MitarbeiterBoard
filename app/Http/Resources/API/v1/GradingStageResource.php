<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GradingStage */
class GradingStageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'grading_system_id' => $this->grading_system_id,
            'title' => $this->name,
            'slug' => $this->slug,
            'symbol' => $this->symbol,
            // Reihenfolge innerhalb des Graduierungssystems (sort_order)
            'level' => (int) $this->sort_order,
            'is_default' => (bool) $this->is_default,
            'badge_image_url' => $this->image_url,
        ];
    }
}
