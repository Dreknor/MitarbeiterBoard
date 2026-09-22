<?php

namespace App\Http\Requests\Personal;

use App\Enums\ContractType;
use App\Enums\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest für das Anlegen und Bearbeiten von Anstellungen.
 * Ersetzt die inline-Validierung in ContractController::store()/update().
 */
class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorisierung erfolgt über Policy im Controller
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'employment_type'       => ['required', 'string', Rule::in(array_column(EmploymentType::cases(), 'value'))],
            'contract_type'         => ['required', 'string', Rule::in(array_column(ContractType::cases(), 'value'))],
            'start'                 => ['required', 'date'],
            'end'                   => ['nullable', 'date', 'after_or_equal:start'],
            'hours'                 => ['required', 'numeric', 'min:1', 'max:168'],
            'hour_type_id'          => ['nullable', 'integer', 'exists:hour_types,id'],
            'department_id'         => ['nullable', 'integer', 'exists:groups,id'],
            'probation_end'         => ['nullable', 'date'],
            'notice_period'         => ['nullable', 'string', 'max:50'],
            'comment'               => ['nullable', 'string', 'max:1000'],
            'is_amendment'          => ['boolean'],
            'amendment_description' => ['nullable', 'string', 'max:500'],
            'is_internal_transfer'  => ['boolean'],
            // Gehalt (optional, nur wenn berechtigt)
            'salary_group'          => ['nullable', 'string', 'max:20'],
            'salary_level'          => ['nullable', 'string', 'max:20'],
            'salary_table_id'       => ['nullable', 'integer', 'exists:pers_salary_tables,id'],
        ];

        // Lehrer-spezifische Felder
        if ($this->input('employment_type') === EmploymentType::Lehrer->value) {
            $rules = array_merge($rules, [
                'school_type_id'     => ['required', 'integer', 'exists:pers_school_types,id'],
                'deputat_hours'      => ['required', 'numeric', 'min:0'],
                'reduction_hours'    => ['nullable', 'numeric', 'min:0'],
                'reduction_reason'   => ['nullable', 'string', 'max:200'],
                'anrechnungsstunden' => ['nullable', 'numeric', 'min:0'],
            ]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'employment_type.required' => 'Bitte wählen Sie einen Anstellungstyp.',
            'employment_type.in'       => 'Ungültiger Anstellungstyp.',
            'contract_type.required'   => 'Bitte wählen Sie einen Vertragstyp.',
            'contract_type.in'         => 'Ungültiger Vertragstyp.',
            'start.required'           => 'Das Startdatum ist erforderlich.',
            'end.after_or_equal'       => 'Das Enddatum muss nach dem Startdatum liegen.',
            'hours.required'           => 'Bitte geben Sie die Wochenstunden an.',
            'hours.min'                => 'Wochenstunden müssen mindestens 1 betragen.',
            'hours.max'                => 'Wochenstunden dürfen maximal 168 betragen.',
            'school_type_id.required'  => 'Für Lehrkräfte ist die Schulart erforderlich.',
            'deputat_hours.required'   => 'Für Lehrkräfte ist das Deputat erforderlich.',
        ];
    }
}

