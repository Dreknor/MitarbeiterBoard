<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class editInventoryItemRequest extends FormRequest
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

                'name' => ['required', 'string'],
                'description' => ['nullable', 'string'],
                'date' => ['nullable', 'date'],
                'price' => ['nullable', 'numeric'],
                'location_id' => ['required', 'exists:inv_locations,id'],
                'category_id' => ['required', 'exists:inv_categories,id'],
                'lieferanten_id' =>   ['nullable', 'exists:inv_lieferanten,id'],
                'oldInvNumber' =>   ['nullable', 'string'],
                'status' =>   ['nullable', 'string'],

        ];
    }
}
