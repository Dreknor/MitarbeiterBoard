<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'time_recording_key' => [
                'nullable', 'numeric', 'digits_between:6,12',
            ],
            'secret_key' => [
                'nullable', 'numeric', 'digits_between:6,10',
            ],
            'mail_timesheet' => [
                'nullable', 'numeric', 'digits:1', 'min:0', 'max:1',
            ],
            'send_mails_if_absence' => [
                'nullable', 'numeric', 'digits:1', 'min:0', 'max:1',
            ],
        ];
    }
}
