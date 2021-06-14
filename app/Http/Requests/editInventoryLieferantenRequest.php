<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class editInventoryLieferantenRequest extends FormRequest
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
            'name' => ['required', 'string', Rule::unique('inv_lieferanten')->ignore($this->lieferant)],
            'kuerzel' => ['required', 'string', Rule::unique('inv_lieferanten')->ignore($this->lieferant)],
        ];
    }
}
