<?php

namespace App\Http\Requests\personal;

use Illuminate\Foundation\Http\FormRequest;

class createHolidayRequest extends FormRequest
{
    /**
     * Die eigentliche Berechtigung (für wen darf erfasst werden) prüft die HolidayPolicy.
     */
    public function authorize(): bool
    {
        return $this->user()->can('has holidays') || $this->user()->can('approve holidays');
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'employe_id' => ['required', function ($attribute, $value, $fail) {
                if ($value !== 'all' && !ctype_digit((string) $value)) {
                    $fail('Ungültige Auswahl.');
                }
            }],
            'half_day' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Das Enddatum darf nicht vor dem Startdatum liegen.',
        ];
    }
}
