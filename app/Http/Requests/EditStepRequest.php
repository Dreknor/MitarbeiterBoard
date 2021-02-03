<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditStepRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('view procedures');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'parent' => 'nullable|exists:procedure_steps,id',
            'position_id' => 'required|exists:positions,id',
            'name'=>    'required|string',
            'description'=>'string|nullable',
            'durationDays'=>'integer|min:1',
        ];
    }
}
