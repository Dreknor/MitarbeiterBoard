<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createShareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('share theme');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'theme' => 'required|string',
            'active_until' => 'sometimes|nullable|date',
            'readonly'  => 'required|boolean',
        ];
    }
}
