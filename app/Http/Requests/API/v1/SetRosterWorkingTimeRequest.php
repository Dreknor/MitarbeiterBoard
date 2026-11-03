<?php

namespace App\Http\Requests\API\v1;

/**
 * PUT /api/v1/roster/rosters/{roster}/working-times – Zielzustand der Arbeitszeit einer Person an einem Tag
 * (start/end leer = frei).
 */
class SetRosterWorkingTimeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'employe_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['nullable', 'required_with:end', 'date_format:H:i'],
            'end' => ['nullable', 'required_with:start', 'date_format:H:i', 'after:start'],
            'function' => ['nullable', 'string', 'max:190'],
        ];
    }
}
