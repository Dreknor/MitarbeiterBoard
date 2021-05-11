<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create types');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => [
                'alpha', 'unique:types,type'
            ]
        ];
    }
}
