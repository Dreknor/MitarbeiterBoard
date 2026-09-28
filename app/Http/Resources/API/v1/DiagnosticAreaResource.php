<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Diagnosebereich mit Kompetenzstufen und Kriterien (Katalog).
 *
 * @mixin \App\Models\DiagnosticArea
 */
class DiagnosticAreaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => (int) $this->sort_order,
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn ($stage) => [
                'id' => $stage->id,
                'title' => $stage->name,
                'code' => $stage->code,
                'goal_description' => $stage->goal_description,
                'sort_order' => (int) $stage->sort_order,
                'criteria' => $stage->relationLoaded('goals') ? $stage->goals->map(fn ($goal) => [
                    'id' => $goal->id,
                    'code' => $goal->code,
                    'description' => $goal->description,
                    'sort_order' => (int) $goal->sort_order,
                ])->values() : [],
            ])->values()),
        ];
    }
}
