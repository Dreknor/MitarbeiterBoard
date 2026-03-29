<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateHortPlanungPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'ab_monat'       => ['required', 'date'],
            'stunden_gesamt' => ['nullable', 'numeric', 'min:0'],
            'stunden_stadt'  => ['nullable', 'numeric', 'min:0'],
            'kommentar'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'ab_monat.required' => 'Bitte einen Startmonat für die Änderung angeben.',
        ];
    }
}

