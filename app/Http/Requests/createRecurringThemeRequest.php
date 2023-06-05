<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createRecurringThemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('manage recurring themes');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'theme' => 'required|string',
            'goal' => 'required|string',
            'type'     => 'required|exists:types,id',
            'information' => 'nullable|string',
            'month'  => ['required','integer','min:1', 'max:12'],
        ];
    }
}
