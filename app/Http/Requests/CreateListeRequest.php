<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateListeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create Terminliste');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'listenname'    => [
                'required',
                'string',
            ],
            'type'          => [
                'required',
                'in:termin,eintrag',
            ],
            'visible_for_all'   => [
                'required',
                'boolean',
            ],
            'multiple'   => [
                'required',
                'boolean',
            ],
            'active'        => [
                'required',
                'boolean',
            ],
            'ende'          => [
                'required',
                'date',
            ],
            ];
    }
}
