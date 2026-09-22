<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class CreateHortPlanungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'beschreibung'  => ['nullable', 'string'],
            'department_id' => ['required', 'exists:groups,id'],
            'start_monat'   => ['required', 'date_format:Y-m'],
            'end_monat'     => ['required', 'date_format:Y-m', 'after_or_equal:start_monat'],
            'typ'           => ['nullable', 'in:planung,rueckblick'],
            'kinderanzahl'  => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Der Name der Planung ist erforderlich.',
            'department_id.required' => 'Bitte eine Abteilung auswählen.',
            'department_id.exists'   => 'Die gewählte Abteilung existiert nicht.',
            'start_monat.required'   => 'Ein Startmonat ist erforderlich.',
            'end_monat.required'     => 'Ein Endmonat ist erforderlich.',
            'end_monat.after'        => 'Der Endmonat muss nach dem Startmonat liegen.',
            'start_monat.date_format' => 'Bitte ein gültiges Jahr-Monat-Format wählen.',
            'end_monat.date_format'   => 'Bitte ein gültiges Jahr-Monat-Format wählen.',
        ];
    }
}

