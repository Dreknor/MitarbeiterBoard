<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaedDiaryCategory */
class PaedDiaryCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'is_global' => $this->isGlobal(),
            'is_hidden' => (bool) ($this->is_hidden ?? false),
        ];
    }
}
