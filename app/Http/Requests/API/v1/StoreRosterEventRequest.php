<?php

namespace App\Http\Requests\API\v1;

/**
 * Termin im Dienstplan anlegen (employe_ids, leer = Merkliste) bzw. ändern (employe_id, null = Merkliste).
 */
class StoreRosterEventRequest extends ApiRequest
{
    public function rules(): array
    {
        $rules = [
            'event' => ['required', 'string', 'max:190'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
        ];

        if ($this->isMethod('PUT')) {
            $rules['employe_id'] = ['present', 'nullable', 'integer', 'exists:users,id'];
        } else {
            $rules['employe_ids'] = ['nullable', 'array', 'max:100'];
            $rules['employe_ids.*'] = ['integer', 'exists:users,id'];
        }

        return $rules;
    }
}
