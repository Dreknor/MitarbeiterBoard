<?php

namespace App\Http\Requests\API\v1;

/**
 * PUT /api/v1/timesheets/days/{day} – Zeitbuchung ändern (Gutschriften sind nicht änderbar).
 */
class UpdateTimesheetDayRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'start' => ['required', 'date_format:H:i', 'before:end'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
            'pause' => ['nullable', 'integer', 'min:0', 'max:600'],
            'comment' => ['nullable', 'string', 'max:60'],
        ];
    }
}
