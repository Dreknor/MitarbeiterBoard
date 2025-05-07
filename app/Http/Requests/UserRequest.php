<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit users');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $this->user->id,
            'changePassword' => 'nullable|boolean',
            'kuerzel' => 'nullable|string|max:15',
            'absence_abo_daily' => 'nullable|boolean',
            'absence_abo_now' => 'nullable|boolean',
            'username' => 'nullable|string|max:255',
            'remind_assign_themes' => 'nullable|boolean',
            'send_mails_if_absence' => 'nullable|boolean',
            'superior_id' => 'nullable|exists:users,id',

        ];
    }
}
