<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
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
            'event' => ['required', 'string'],
            'date' => ['required', 'date'],
            'start' => ['required', 'date_format:H:i', 'before:end'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
            'employes' => ['required', 'array'],
            'employes.*' => ['numeric', 'exists:users,id']
        ];
    }
}
