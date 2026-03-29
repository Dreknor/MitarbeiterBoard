<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHortZusatzTypRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        $planungId = $this->route('planung')?->id;

        return [
            'kuerzel'     => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('hort_zusatzstunden_typen')->where('hort_planung_id', $planungId),
            ],
            'bezeichnung' => ['required', 'string', 'max:255'],
            'position'    => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'kuerzel.required'    => 'Ein Kürzel ist erforderlich.',
            'kuerzel.alpha_dash'   => 'Das Kürzel darf nur Buchstaben, Zahlen, Binde- und Unterstriche enthalten.',
            'kuerzel.unique'       => 'Dieses Kürzel wird in dieser Planung bereits verwendet.',
            'bezeichnung.required' => 'Eine Bezeichnung ist erforderlich.',
        ];
    }
}

