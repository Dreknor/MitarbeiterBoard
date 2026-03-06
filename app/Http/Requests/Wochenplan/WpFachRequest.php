<?php

namespace App\Http\Requests\Wochenplan;

use Illuminate\Foundation\Http\FormRequest;

class WpFachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage wochenplan-faecher');
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
