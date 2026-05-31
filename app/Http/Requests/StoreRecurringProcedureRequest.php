<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringProcedureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('manage procedures');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:120',
            'procedure_id'      => 'required|exists:procedures,id',
            'faelligkeit_typ'   => 'required|in:datum,vor_ferien,nach_ferien,wochentag,schuljahres_stichtag',
            'month'             => 'nullable|integer|min:1|max:12|required_if:faelligkeit_typ,datum',
            'wochen'            => 'nullable|integer|min:0|max:52|required_if:faelligkeit_typ,vor_ferien|required_if:faelligkeit_typ,nach_ferien',
            'ferien'            => 'nullable|in:Sommerferien,Herbstferien,Weihnachtsferien,Winterferien,Osterferien|required_if:faelligkeit_typ,vor_ferien|required_if:faelligkeit_typ,nach_ferien',
            'weekday'           => 'nullable|integer|min:0|max:6|required_if:faelligkeit_typ,wochentag',
            'weekday_interval'  => 'nullable|integer|min:1|max:12',
            'schuljahres_tag'   => 'nullable|integer|min:1|max:31|required_if:faelligkeit_typ,schuljahres_stichtag',
            'schuljahres_monat' => 'nullable|integer|min:1|max:12|required_if:faelligkeit_typ,schuljahres_stichtag',
            'active'            => 'nullable|boolean',
        ];
    }
}
