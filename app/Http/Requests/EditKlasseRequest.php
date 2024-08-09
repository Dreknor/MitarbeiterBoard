<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditKlasseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit klassen');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' =>[
                'required',
                'unique:klassen,name,'.$this->klasse->id
            ],
            'kuerzel' => [
                'nullable',
                'unique:klassen,kuerzel',
            ]
        ];
    }
}
