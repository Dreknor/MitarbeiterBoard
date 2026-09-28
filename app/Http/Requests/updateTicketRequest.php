<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class updateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('ticket'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|nullable|integer|exists:ticket_categories,id',
            'priority' => 'sometimes|required|in:low,medium,high',
        ];
    }
}
