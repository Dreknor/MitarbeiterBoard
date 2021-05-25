<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createWPRow extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create Wochenplan');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => [
                'required',
                'string'
            ],
            'wochenplan_id' => [
                'required',
                'exists:wochenplaene,id'
            ]
        ];
    }
}
