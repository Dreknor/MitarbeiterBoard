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
            'name'                   => ['required', 'string', 'max:255'],
            'beschreibung'           => ['nullable', 'string'],
            'schriftgroesse'         => ['required', 'in:normal,gross,sehr_gross'],
            'schriftart'             => ['nullable', 'string', 'max:100'],
            'blade_template'         => ['required', 'string', 'max:255'],
            'is_default'             => ['sometimes', 'boolean'],
            // layout_config Einzelfelder
            'margin_top'             => ['nullable', 'integer', 'min:0', 'max:50'],
            'margin_bottom'          => ['nullable', 'integer', 'min:0', 'max:50'],
            'margin_left'            => ['nullable', 'integer', 'min:0', 'max:50'],
            'margin_right'           => ['nullable', 'integer', 'min:0', 'max:50'],
            'col_fach'               => ['nullable', 'integer', 'min:5', 'max:50'],
            'col_aufgaben'           => ['nullable', 'integer', 'min:20', 'max:90'],
            'col_check'              => ['nullable', 'integer', 'min:0', 'max:20'],
            'col_unterschrift'       => ['nullable', 'integer', 'min:0', 'max:40'],
            'zeige_dauer_spalte'     => ['sometimes', 'boolean'],
            'zeige_name_feld'        => ['sometimes', 'boolean'],
            'zeige_klasse'           => ['sometimes', 'boolean'],
            'zeige_zeitraum'         => ['sometimes', 'boolean'],
            'zeige_selbsteinschaetzung' => ['sometimes', 'boolean'],
            'zeige_unterschrift'     => ['sometimes', 'boolean'],
        ];
    }
}
