<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('view tickets');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'sometimes|exists:ticket_categories,id',
            'priority' => 'required|in:low,medium,high',
            'file' => 'sometimes|file|max:10240',
        ];
    }
}
