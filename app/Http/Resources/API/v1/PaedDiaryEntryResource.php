<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaedDiaryEntry */
class PaedDiaryEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'class_id' => $this->klasse_id,
            'schueler_ids' => $this->whenLoaded('schueler', fn () => $this->schueler->pluck('id')->map(fn ($id) => (int) $id)->values()),
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'category_color' => $this->category?->color,
            'entry_date' => $this->datum?->toDateString(),
            'content' => $this->content,
            'is_dossier_only' => (bool) $this->dossier_only,
            'is_completed' => $this->completed_at !== null,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_by_id' => $this->user_id,
            'created_by_name' => $this->user?->name,
            'is_own' => $user ? (int) $this->user_id === (int) $user->id : false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Nur im Klassen-Feed: Schüler des Eintrags (Vorname + Initial)
            'students' => $this->when($this->resource->getAttribute('students_brief') !== null, fn () => $this->resource->getAttribute('students_brief')),
        ];
    }
}
