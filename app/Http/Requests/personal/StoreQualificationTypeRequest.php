<?php

namespace App\Http\Requests\personal;

use App\Enums\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest für das Anlegen/Aktualisieren von Qualifikationstypen
 * (Vorgaben der Qualifikationsmatrix).
 */
class StoreQualificationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employmentTypes = array_column(EmploymentType::cases(), 'value');

        return [
            'name'            => ['required', 'string', 'max:190'],
            'category'        => ['required', 'string', Rule::in(['pflicht', 'empfohlen', 'optional'])],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'reminder_days'   => ['nullable', 'integer', 'min:0', 'max:365'],
            'applies_to'      => ['nullable', 'array'],
            'applies_to.*'    => ['string', Rule::in($employmentTypes)],
            'description'     => ['nullable', 'string', 'max:2000'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Bitte geben Sie einen Namen an.',
            'category.required'      => 'Bitte wählen Sie eine Kategorie.',
            'category.in'            => 'Kategorie muss Pflicht, Empfohlen oder Optional sein.',
            'validity_months.min'    => 'Die Laufzeit muss mindestens 1 Monat betragen.',
            'reminder_days.min'      => 'Die Erinnerungsfrist darf nicht negativ sein.',
            'applies_to.*.in'        => 'Ungültiger Anstellungstyp.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'       => $this->boolean('is_active'),
            'validity_months' => $this->input('validity_months') === '' ? null : $this->input('validity_months'),
            'reminder_days'   => $this->input('reminder_days') === '' ? null : $this->input('reminder_days'),
        ]);
    }
}

