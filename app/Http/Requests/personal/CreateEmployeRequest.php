<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmployeRequest extends FormRequest
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
            'familienname' => ['required', 'string'],
            'vorname' => ['required', 'string'],
            'geburtstag' => ['required', 'date'],
            'geburtsort' => ['nullable', 'string'],
            'geburtsname' => ['nullable', 'string'],
            'geschlecht' => ['required', Rule::in(['männlich', 'weiblich', 'anderes'])],
            'schwerbehindert' => ['required', Rule::in([1, 0])],
            'sozialversicherungsnummer' => ['nullable', 'string'],
            'staatsangehoerigkeit' => ['required', 'string'],
            'google_calendar_link' => ['nullable', 'string'],
            'caldav_working_time' => ['nullable', 'integer', 'min:0', 'max:1'],
            'caldav_events' => ['nullable', 'integer', 'min:0', 'max:1'],
        ];
    }
}
