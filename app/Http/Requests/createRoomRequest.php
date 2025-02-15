<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class createRoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('manage rooms');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('rooms')->whereNull('deleted_at')
            ],
            'room_number' => [
                'required',
                'string',
                'max:10',
                Rule::unique('rooms')->whereNull('deleted_at')
            ],
            'week' => [
                'nullable',
                Rule::in(['A', 'B'])
            ],
            'start' => [
                'required',
                'date_format:H:i',
                'before:end'
            ],
            'end' => [
                'required',
                'date_format:H:i',
                'after:start'
            ],
            'indiware_shortname' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('rooms')->whereNull('deleted_at')
            ]
        ];
    }
}
