<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class selfUpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'vorname' => 'required|string|max:255',
            'familienname' => 'required|string|max:255',
            'geburtstag' => 'required|date',
            'geburtsname' => 'nullable|string|max:255',
            'geburtsort' => 'nullable|string|max:255',
            'staatsangehoerigkeit' => 'nullable|string|max:255',
            'schwerbehindert' => 'nullable|boolean',
            'geschlecht' => 'string|max:255',
            'google_calendar_link' => 'nullable|string|max:255',
            'caldav_working_time' => 'nullable|boolean',
            'caldav_events' => 'nullable|boolean',
            'sozialversicherungsnummer' => 'nullable|string|max:255',
        ];
    }
}
