<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:255'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'book_room' => ['nullable', 'boolean'],
            'room_id' => [
                'nullable',
                'required_if:book_room,1',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('bookable', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required_if' => 'Bitte einen buchbaren Raum auswählen.',
            'room_id.exists' => 'Der ausgewählte Raum ist nicht buchbar oder nicht vorhanden.',
        ];
    }
}

