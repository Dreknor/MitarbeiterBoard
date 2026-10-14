<?php

namespace App\Http\Requests\API\v1;

/**
 * POST /api/v1/timesheets/{timesheet}/days – Zeitbuchung an einem Tag des Nachweises.
 */
class StoreTimesheetDayRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i', 'before:end'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
            'pause' => ['nullable', 'integer', 'min:0', 'max:600'],
            'comment' => ['nullable', 'string', 'max:60'],
        ];
    }
}
