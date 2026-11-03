<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Graduierungs-Session in Listen (ohne Fragen/Antworten), mit Fortschritt.
 * Das Attribut "progress" wird vom Controller gesetzt (GradingSessionService::progress()).
 *
 * @mixin \App\Models\GradingDocumentationSession
 */
class GradingSessionListResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();
        $progress = $this->progress ?? null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'answer_order_mode' => $this->answer_order_mode,
            'class_id' => $this->klasse_id,
            'schueler_id' => $this->schueler_id,
            'schueler_name' => $this->whenLoaded('schueler', fn () => $this->schueler ? trim($this->schueler->vorname . ' ' . $this->schueler->nachname) : null),
            'group_id' => $this->group_id,
            'grading_system' => $this->whenLoaded('gradingSystem', fn () => $this->gradingSystem ? [
                'id' => $this->gradingSystem->id,
                'name' => $this->gradingSystem->name,
            ] : null),
            'created_by_id' => $this->user_id,
            'created_by_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'is_owner' => $user ? (int) $this->user_id === (int) $user->id : false,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_completed' => $this->completed_at !== null,
            'progress' => $progress,
        ];
    }
}
