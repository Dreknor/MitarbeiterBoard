<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHortZusatzTypRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'bezeichnung' => ['sometimes', 'string', 'max:255'],
            'position'    => ['sometimes', 'integer', 'min:1'],
            'aktiv'       => ['sometimes', 'boolean'],
        ];
    }
}

