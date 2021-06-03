<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class editLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit inventar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'kennzeichnung' => ['string',Rule::unique('inv_locations')->ignore($this->location->id)],
            'name' => ['required', 'string', Rule::unique('inv_locations')->ignore($this->location->id)],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'user' => ['nullable', 'integer'],
        ];
    }
}
