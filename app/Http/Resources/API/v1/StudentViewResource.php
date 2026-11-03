<?php

namespace App\Http\Resources\API\v1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Zentrale Schüler-View: Stammdaten + Graduierung + Diagnose + letzte Tagebucheinträge.
 * Die Moduldaten werden vom Controller aggregiert und über withSections() übergeben.
 *
 * @mixin \App\Models\Schueler
 */
class StudentViewResource extends JsonResource
{
    /** Keine "data"-Hülle – die App erwartet die Abschnitte auf oberster Ebene. */
    public static $wrap = null;

    private array $sections = [];

    public function withSections(array $sections): self
    {
        $this->sections = $sections;

        return $this;
    }

    public function toArray($request): array
    {
        return [
            'student' => [
                'id' => $this->id,
                'firstname' => $this->vorname,
                'lastname' => $this->nachname,
                'class_id' => $this->klasse_id,
                'class_name' => $this->klasse?->name,
                'date_of_birth' => $this->geburtsdatum?->toDateString(),
            ],
            'grading_overview' => $this->sections['grading_overview'] ?? null,
            'diagnostic_overview' => $this->sections['diagnostic_overview'] ?? null,
            'recent_paed_diary_entries' => PaedDiaryEntryResource::collection($this->sections['recent_paed_diary_entries'] ?? collect()),
            'permissions' => $this->sections['permissions'] ?? [],
        ];
    }
}
