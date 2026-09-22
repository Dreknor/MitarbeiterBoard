<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHortPlanungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string'],
            'aktiv'        => ['nullable', 'boolean'],
            'start_monat'  => ['nullable', 'date_format:Y-m'],
            'end_monat'    => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_monat.date_format' => 'Bitte ein gültiges Jahr-Monat-Format für den Startmonat wählen.',
            'end_monat.date_format'   => 'Bitte ein gültiges Jahr-Monat-Format für den Endmonat wählen.',
        ];
    }
}

