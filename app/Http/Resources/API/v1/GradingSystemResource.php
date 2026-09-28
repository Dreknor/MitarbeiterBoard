<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GradingSystem */
class GradingSystemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'active' => (bool) $this->active,
            'stages' => GradingStageResource::collection($this->whenLoaded('stages')),
        ];
    }
}
