<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return   auth()->user()->can('edit employe');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'holidayClaim' => [
                'required', 'integer', 'min:1',
            ],
            'date_start' => [
                'required', 'date',
            ],
            // Ein Key darf nur einer Person gehören – sonst ist die Anmeldung am Zeiterfassungs-Terminal mehrdeutig
            'time_recording_key' => [
                'nullable', 'numeric', 'digits_between:6,12',
                Rule::unique('employes_data', 'time_recording_key')->ignore($this->route('employe')?->employe_data?->id),
            ],
            'secret_key' => [
                'nullable', 'numeric', 'digits_between:6,10',
            ],
            'mail_timesheet' => [
                'nullable', 'boolean',
            ],
            'google_calendar_link' => [
                'nullable', 'string', 'max:1000',
            ],
            'caldav_working_time' => [
                'nullable', 'boolean',
            ],
            'caldav_events' => [
                'nullable', 'boolean',
            ],
            'send_mails_if_absence' => [
                'nullable', 'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'time_recording_key.unique' => 'Dieser Zeiterfassungs-Key ist bereits einer anderen Person zugeordnet.',
            'holidayClaim.required'     => 'Bitte den Urlaubsanspruch angeben.',
        ];
    }
}
