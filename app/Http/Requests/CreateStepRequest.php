<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStepRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('manage procedures');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // Vorgänger muss zum selben Prozess gehören
            'parent' => ['nullable', Rule::exists('procedure_steps', 'id')->where('procedure_id', $this->route('procedure')?->id)],
            'position_id' => 'required|exists:positions,id',
            'name'=>    'required|string|max:60',
            'description'=>'string|nullable',
            'durationDays'=>'integer|min:1',
            'endDate'=>'nullable|date',
        ];
    }
}
