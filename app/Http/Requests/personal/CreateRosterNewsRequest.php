<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class CreateRosterNewsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create roster');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'news' => ['required', 'string']
        ];
    }
}
