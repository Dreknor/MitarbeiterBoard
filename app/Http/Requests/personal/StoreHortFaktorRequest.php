<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHortFaktorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        $planungId = $this->route('planung')?->id;

        return [
            'kuerzel'              => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('hort_faktoren')->where('hort_planung_id', $planungId),
            ],
            'bezeichnung'          => ['required', 'string', 'max:255'],
            'berechnungs_typ'      => ['required', 'in:divisor,faktor_auf_bs,faktor_auf_summe'],
            'position'             => ['required', 'integer', 'min:1'],
            'wert'                 => ['required', 'numeric', 'min:0'],
            'gueltig_ab'           => ['required', 'date'],
            'gesetzliche_grundlage' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'kuerzel.required'         => 'Ein Kürzel ist erforderlich.',
            'kuerzel.alpha_dash'        => 'Das Kürzel darf nur Buchstaben, Zahlen, Binde- und Unterstriche enthalten.',
            'kuerzel.unique'            => 'Dieses Kürzel wird in dieser Planung bereits verwendet.',
            'bezeichnung.required'      => 'Eine Bezeichnung ist erforderlich.',
            'berechnungs_typ.required'  => 'Bitte einen Berechnungstyp auswählen.',
            'berechnungs_typ.in'        => 'Ungültiger Berechnungstyp.',
            'position.required'         => 'Eine Position ist erforderlich.',
            'wert.required'             => 'Ein Faktor-Wert ist erforderlich.',
            'gueltig_ab.required'       => 'Bitte ein Gültigkeitsdatum angeben.',
        ];
    }
}

