<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createLocationTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:inv_locationtypes'],
            'description' => ['nullable', 'string'],
        ];
    }
}
