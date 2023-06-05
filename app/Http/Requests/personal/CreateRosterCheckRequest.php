<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class CreateRosterCheckRequest extends FormRequest
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
            'check_name' => ['string', 'required'],
            'field_name' => ['string', 'required'],
            'operator' => ['string', 'required'],
            'value' => ['string', 'required'],
            'weekday' => ['required', 'array', 'min:1'],
            'weekday.*' => ['integer', 'min:0', 'max:6'],
            'needs' => ['integer', 'min:1', 'required'],
            'department_id' => ['required', 'exists:groups,id'],
        ];
    }
}
