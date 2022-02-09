<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class exportVertretungenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit vertretungen');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date']
        ];
    }
}
