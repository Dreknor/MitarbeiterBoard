<?php

namespace App\Http\Requests;

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
        ];
    }
}
