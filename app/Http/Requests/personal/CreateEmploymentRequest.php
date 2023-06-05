<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmploymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit employe');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'department_id' => ['required', 'exists:groups,id'],
            'hour_type_id' => ['required', 'exists:hour_types,id'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
            'hours' => ['required', 'numeric'],
            'comment' => ['nullable', 'string'],
            'replaced_employment_id' => ['nullable', 'exists:employments,id'],
        ];
    }
}
