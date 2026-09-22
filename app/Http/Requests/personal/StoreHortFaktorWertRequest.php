<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHortFaktorWertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        $faktorId = $this->route('faktor')?->id;

        return [
            'wert'      => ['required', 'numeric', 'min:0'],
            'gueltig_ab' => [
                'required',
                'date',
                Rule::unique('hort_faktor_werte')->where('hort_faktor_id', $faktorId),
            ],
            'notiz'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'wert.required'       => 'Ein Wert ist erforderlich.',
            'gueltig_ab.required' => 'Bitte ein Gültigkeitsdatum angeben.',
            'gueltig_ab.unique'   => 'Für dieses Datum existiert bereits ein Wert. Bitte den bestehenden Eintrag bearbeiten.',
        ];
    }
}

