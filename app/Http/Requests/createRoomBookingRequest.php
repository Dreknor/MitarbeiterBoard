<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createRoomBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('manage recurring themes');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'room_id' => [
                'exists:rooms,id',
                'required'
            ],
            'weekday' => ['integer', 'min:1', 'max:5', 'required'],
            'start'  => ['required', 'date_format:H:i', 'before:end'],
            'end'  => ['required', 'date_format:H:i', 'after:start'],
            'name' => ['required', 'string']

        ];
    }
}
