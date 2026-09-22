<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateHortPlanungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bitte einen Namen für das neue Szenario angeben.',
        ];
    }
}

