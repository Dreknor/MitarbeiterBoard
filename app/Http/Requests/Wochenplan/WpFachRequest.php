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
            'name'         => ['required', 'string', 'max:100'],
            'sort_order'   => ['sometimes', 'integer', 'min:0'],
            'is_default'   => ['sometimes', 'boolean'],
            'symbol_typ'   => ['sometimes', 'nullable', 'in:emoji,svg,bild,keine'],
            'symbol_wert'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'symbol_farbe' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'symbol_bild'  => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
