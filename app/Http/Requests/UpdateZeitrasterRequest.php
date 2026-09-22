<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZeitrasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage zeitraster');
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:100',
                                     Rule::unique('zeitraster', 'name')->ignore($this->zeitraster)],
            'beschreibung'       => 'nullable|string|max:1000',
            'ist_standard'       => 'nullable|boolean',
            'stunden'            => 'nullable|array',
            'stunden.*.period'   => 'required|integer|min:1|max:15',
            'stunden.*.start'    => 'required|date_format:H:i',
            'stunden.*.end'      => 'required|date_format:H:i|after:stunden.*.start',
            'stunden.*.week'     => 'nullable|string|max:5',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'               => 'Der Name ist erforderlich.',
            'name.unique'                 => 'Dieses Zeitraster existiert bereits.',
            'stunden.*.period.required'   => 'Die Stundennummer ist erforderlich.',
            'stunden.*.start.date_format' => 'Startzeit muss im Format HH:MM angegeben werden.',
            'stunden.*.end.date_format'   => 'Endzeit muss im Format HH:MM angegeben werden.',
            'stunden.*.end.after'         => 'Die Endzeit muss nach der Startzeit liegen.',
        ];
    }
}

