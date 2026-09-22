<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHortPlanungMonatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'kinderanzahl'    => ['sometimes', 'integer', 'min:0'],
            'vollzeitstunden' => ['sometimes', 'numeric', 'min:0.01'],
            'notiz'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'vollzeitstunden.min' => 'Die Vollzeitstunden müssen größer als 0 sein.',
        ];
    }
}

