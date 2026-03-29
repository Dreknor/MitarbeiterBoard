<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHortFaktorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'bezeichnung'          => ['sometimes', 'string', 'max:255'],
            'berechnungs_typ'      => ['sometimes', 'in:divisor,faktor_auf_bs,faktor_auf_summe'],
            'position'             => ['sometimes', 'integer', 'min:1'],
            'aktiv'                => ['sometimes', 'boolean'],
            'gesetzliche_grundlage' => ['nullable', 'string', 'max:255'],
        ];
    }
}

