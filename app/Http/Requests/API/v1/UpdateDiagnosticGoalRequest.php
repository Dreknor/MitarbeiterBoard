<?php

namespace App\Http\Requests\API\v1;

use App\Models\DiagnosticDevelopmentGoal;
use Illuminate\Validation\Rule;

class UpdateDiagnosticGoalRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:500'],
            'status' => ['sometimes', Rule::in(array_diff(DiagnosticDevelopmentGoal::STATUSES, [DiagnosticDevelopmentGoal::STATUS_ARCHIVED]))],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'completion_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'completed_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Ungültiger Status. Erlaubt: open, in_progress, achieved, not_achieved (Archivieren über DELETE).',
        ];
    }
}
