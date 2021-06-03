<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit inventar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'kennzeichnung' => ['nullable', 'string', 'unique:inv_locations'],
            'name' => ['required', 'string', 'unique:inv_locations'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'user' => ['nullable', 'integer'],
        ];
    }
}
