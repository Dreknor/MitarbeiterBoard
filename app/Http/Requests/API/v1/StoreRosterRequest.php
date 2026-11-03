<?php

namespace App\Http\Requests\API\v1;

/**
 * POST /api/v1/roster/rosters – Dienstplan (oder Vorlage) für eine Woche anlegen.
 */
class StoreRosterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:groups,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', 'in:normal,template'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'template_id' => ['nullable', 'integer', 'exists:rosters,id,deleted_at,NULL'],
            'additional_weeks' => ['nullable', 'array', 'max:26'],
            'additional_weeks.*' => ['date_format:Y-m-d'],
        ];
    }
}
