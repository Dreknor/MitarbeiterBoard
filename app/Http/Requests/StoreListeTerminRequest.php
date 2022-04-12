<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreListeTerminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'termin'    => [
                'required',
                'date',
            ],
            'zeit' => [
                'required',
                'date_format:H:i',
            ],
            'comment' => [
                'string',
                'nullable',
            ],
            'weekly' => [
                'integer',
                'max:1',
                'nullable',
            ],
            'repeat' => [
                'integer',
                'min:1',
                'nullable',
            ],
            'duration' => [
                'integer',
                'nullable',
            ],
        ];
    }
}
