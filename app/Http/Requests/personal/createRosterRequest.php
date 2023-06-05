<?php

namespace App\Http\Requests\personal;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class createRosterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create roster');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'start_date' => [
                'required', 'date'
            ],
            'comment' => ['nullable', 'string'],
            'type' => ['required', 'in:normal,template'],
            'used_template' => ['nullable', 'exists:rosters,id'],
            'department_id' => ['required', 'exists:groups,id'],
        ];
    }
}
