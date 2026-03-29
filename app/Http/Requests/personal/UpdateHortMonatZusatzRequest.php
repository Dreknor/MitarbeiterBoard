<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHortMonatZusatzRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'stunden' => ['required', 'numeric', 'min:0'],
            'notiz'   => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'stunden.required' => 'Ein Stundenwert ist erforderlich.',
            'stunden.min'      => 'Stunden dürfen nicht negativ sein.',
        ];
    }
}

