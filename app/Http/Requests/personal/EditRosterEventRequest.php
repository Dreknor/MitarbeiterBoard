<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class EditRosterEventRequest extends FormRequest
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
            'event' => ['required', 'string', 'max:190'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i', 'before:end'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
            // Leer = Termin landet in der Merkliste (nicht zugewiesen)
            'employes' => ['nullable', 'array'],
            'employes.*' => ['integer', 'exists:users,id']
        ];
    }
}
