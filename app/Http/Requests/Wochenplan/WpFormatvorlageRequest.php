<?php

namespace App\Http\Requests\Wochenplan;

use Illuminate\Foundation\Http\FormRequest;

class WpFormatvorlageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage wochenplan-formatvorlagen');
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'beschreibung'   => ['nullable', 'string'],
            'schriftgroesse' => ['required', 'in:normal,gross,sehr_gross'],
            'schriftart'     => ['nullable', 'string', 'max:100'],
            'layout_config'  => ['nullable', 'array'],
            'blade_template' => ['required', 'string', 'max:255'],
            'is_default'     => ['sometimes', 'boolean'],
        ];
    }
}
