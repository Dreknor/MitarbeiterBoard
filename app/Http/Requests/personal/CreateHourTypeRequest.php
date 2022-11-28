<?php

namespace App\Http\Requests\personal;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class CreateHourTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit settings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'fulltimehours' => ['numeric', 'required'],
            'minutes' => ['numeric', 'required'],
            'start' => ['date', 'required',],
            'end' => ['nullable', 'date', 'after:start']
        ];
    }
}
