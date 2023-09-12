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
                'nullable', 'integer', 'digits:10',
            ],
            'secret_key' => [
                'nullable', 'integer', 'digits_between:6,10',
            ]
        ];
    }
}
