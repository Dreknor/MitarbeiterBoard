<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class createTicketCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('ticket'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => 'required|string|max:65000',
            'internal' => 'sometimes|boolean',
            'waiting_until' => 'nullable|date|after_or_equal:today',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:20480',
        ];
    }

    public function attributes(): array
    {
        return [
            'comment' => 'Kommentar',
            'waiting_until' => 'Warten bis',
            'files.*' => 'Datei',
        ];
    }
}
