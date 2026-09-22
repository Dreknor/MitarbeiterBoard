<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class AddHortPlanungPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage hort planung');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Bitte einen Mitarbeiter auswählen.',
            'user_id.exists'   => 'Der gewählte Mitarbeiter existiert nicht.',
        ];
    }
}

