<?php

namespace App\Http\Resources\Personal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Gefilterte Daten für Self-Service (ohne sensible Felder wie Gehalt, BEM, fremde Gesprächsnotizen).
 * Verhindert Datenleck bei direktem API-Zugriff.
 */
class EmployeeSelfServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,

            // Stammdaten
            'geburtstag'     => $this->employe_data?->geburtstag,
            'eintrittsdatum' => $this->employments->min('start')?->format('d.m.Y'),

            // Verträge: Typ und Status ja, Gehalt NIEMALS in Self-Service-Resource
            'employments' => $this->employments->map(fn($e) => [
                'id'              => $e->id,
                'employment_type' => $e->employment_type?->label(),
                'contract_type'   => $e->contract_type?->label(),
                'status'          => $e->status?->label(),
                'start'           => $e->start?->format('d.m.Y'),
                'end'             => $e->end?->format('d.m.Y'),
                'hours'           => $e->hours,
                'department'      => $e->department?->name,
                // salary_group, salary_level, salary_table_id ABSICHTLICH ausgelassen
            ]),
        ];
    }
}

