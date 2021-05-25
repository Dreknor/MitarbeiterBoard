<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createWPRequest extends FormRequest
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
            'gueltig_ab' => [
                'required',
                'date',
                'before:gueltig_bis'],
            'gueltig_bis' => [
                'required',
                'date',
                'after:gueltig_ab'],
            'name' => [
                'required',
                'string'
            ],
            'bewertung' => [
                'required',
                'integer',
                'min:0',
                'max:2'
            ]
        ];
    }
}
