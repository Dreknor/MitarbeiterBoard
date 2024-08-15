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
        return auth()->user()->can('view procedures');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'procedure_id' => 'required|exists:procedures,id',
            'month' => 'nullable|integer',
            'faelligkeit_typ' => 'required|in:datum,vor_ferien,nach_ferien',
            'wochen' => 'nullable|integer',
            'ferien' => 'nullable|in:Sommerferien,Herbstferien,Weihnachtsferien,Winterferien,Osterferien',
        ];
    }
}
