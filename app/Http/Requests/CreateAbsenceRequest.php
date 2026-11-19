<?php

namespace App\Http\Requests;

use App\Models\AbsenceSerie;
use Illuminate\Foundation\Http\FormRequest;

class CreateAbsenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('view absences');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'users_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:64'],
            'start'=> ['required', 'date', 'before_or_equal:end'],
            'end'=> ['required', 'date' , 'after_or_equal:start'],
            'before' => ['nullable', 'exists:absences,id'],
            'showVertretungsplan' => ['nullable','integer', 'min:0', 'max:1'],
            'sick_note_required'  => ['nullable','integer', 'min:0', 'max:1'],

            // Wiederkehrende Abwesenheit (Regeln zu Zeitraum/Grund im AbsenceSerieService)
            'wiederkehrend' => ['nullable', 'boolean'],
            'rhythmus' => ['exclude_unless:wiederkehrend,1', 'required', 'in:'.implode(',', array_keys(AbsenceSerie::RHYTHMEN))],
            'wochentage' => ['exclude_unless:rhythmus,woechentlich', 'exclude_unless:wiederkehrend,1', 'required', 'array'],
            'wochentage.*' => ['integer', 'in:'.implode(',', array_keys(AbsenceSerie::WOCHENTAGE))],
            'intervall' => ['exclude_unless:rhythmus,woechentlich', 'exclude_unless:wiederkehrend,1', 'required', 'integer', 'min:1', 'max:4'],
            'monat_position' => ['exclude_unless:rhythmus,monatlich', 'exclude_unless:wiederkehrend,1', 'required', 'integer', 'in:'.implode(',', array_keys(AbsenceSerie::POSITIONEN))],
            'monat_wochentag' => ['exclude_unless:rhythmus,monatlich', 'exclude_unless:wiederkehrend,1', 'required', 'integer', 'in:'.implode(',', array_keys(AbsenceSerie::WOCHENTAGE))],
        ];
    }

    public function attributes(): array
    {
        return [
            'rhythmus' => 'Rhythmus',
            'wochentage' => 'Wochentage',
            'intervall' => 'Intervall',
            'monat_position' => 'Woche im Monat',
            'monat_wochentag' => 'Wochentag',
        ];
    }
}
