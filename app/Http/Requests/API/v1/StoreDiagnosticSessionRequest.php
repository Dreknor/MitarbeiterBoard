<?php

namespace App\Http\Requests\API\v1;

use App\Models\DiagnosticGoal;
use App\Models\DiagnosticStage;
use Illuminate\Validation\Validator;

class StoreDiagnosticSessionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'schueler_id' => ['required', 'integer', $this->existingSchueler()],
            'area_id' => ['required', 'integer', 'exists:diagnostic_areas,id'],
            'stage_id' => ['nullable', 'integer', 'exists:diagnostic_stages,id'],
            'session_date' => ['sometimes', 'date_format:Y-m-d'],
            'assessment_notes' => ['nullable', 'string', 'max:20000'],
            // Standard: Sitzung wird direkt abgeschlossen
            'complete' => ['sometimes', 'boolean'],

            // Optionale Bewertung von Katalogkriterien (Ampel wie im Web-Frontend)
            'assessments' => ['sometimes', 'array', 'max:500'],
            'assessments.*.criterion_id' => ['required', 'integer', 'distinct', 'exists:diagnostic_goals,id'],
            'assessments.*.rating' => ['nullable', 'in:white,gray,dark_gray'],
            'assessments.*.is_current_goal' => ['sometimes', 'boolean'],

            // Individuelle Entwicklungsziele
            'goals' => ['sometimes', 'array', 'max:50'],
            'goals.*.title' => ['required', 'string', 'max:500'],
            'goals.*.target_date' => ['nullable', 'date_format:Y-m-d'],
            'goals.*.criterion_id' => ['nullable', 'integer', 'exists:diagnostic_goals,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $areaId = (int) $this->input('area_id');

            if ($this->filled('stage_id')) {
                $stageAreaId = DiagnosticStage::whereKey($this->input('stage_id'))->value('diagnostic_area_id');
                if ((int) $stageAreaId !== $areaId) {
                    $v->errors()->add('stage_id', 'Die Stufe gehört nicht zum gewählten Diagnosebereich.');
                }
            }

            $criterionIds = collect($this->input('assessments', []))->pluck('criterion_id')
                ->merge(collect($this->input('goals', []))->pluck('criterion_id'))
                ->filter()->unique()->values();

            if ($criterionIds->isNotEmpty()) {
                $valid = DiagnosticGoal::whereIn('diagnostic_goals.id', $criterionIds)
                    ->whereHas('stage', fn ($q) => $q->where('diagnostic_area_id', $areaId))
                    ->pluck('id');
                $invalid = $criterionIds->diff($valid);
                if ($invalid->isNotEmpty()) {
                    $v->errors()->add('assessments', 'Folgende Kriterien gehören nicht zum Diagnosebereich: ' . $invalid->implode(', '));
                }
            }
        });
    }
}
