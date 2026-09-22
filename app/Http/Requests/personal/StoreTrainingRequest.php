<?php

namespace App\Http\Requests\Personal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest für das Anlegen von Fortbildungen.
 */
class StoreTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'provider'              => ['nullable', 'string', 'max:255'],
            'start_date'            => ['required', 'date'],
            'end_date'              => ['required', 'date', 'after_or_equal:start_date'],
            'location'              => ['nullable', 'string', 'max:255'],
            'cost'                  => ['nullable', 'numeric', 'min:0'],
            'max_participants'      => ['nullable', 'integer', 'min:1'],
            'qualification_type_id' => ['nullable', 'exists:pers_qualification_types,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'            => 'Bitte geben Sie einen Titel an.',
            'start_date.required'       => 'Das Startdatum ist erforderlich.',
            'end_date.required'         => 'Das Enddatum ist erforderlich.',
            'end_date.after_or_equal'   => 'Das Enddatum muss nach dem Startdatum liegen.',
            'cost.numeric'              => 'Die Kosten müssen ein numerischer Wert sein.',
            'max_participants.integer'  => 'Die max. Teilnehmerzahl muss eine Ganzzahl sein.',
        ];
    }
}

