<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aufgabe zu einem Thema anlegen – für die ganze Gruppe bzw. alle
 * Meeting-Teilnehmenden (assign = all) oder für ausgewählte Personen.
 */
class StoreThemeTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'task'    => ['required', 'string', 'max:1000'],
            'date'    => ['required', 'date', 'after:today'],
            'assign'  => ['required', Rule::in(['all', 'users'])],
            'users'   => ['required_if:assign,users', 'array'],
            'users.*' => ['integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after'        => 'Das Fälligkeitsdatum muss in der Zukunft liegen.',
            'users.required_if' => 'Bitte mindestens eine Person auswählen.',
        ];
    }

    public function wholeContext(): bool
    {
        return $this->input('assign') === 'all';
    }

    public function userIds(): array
    {
        return collect($this->input('users', []))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }
}
