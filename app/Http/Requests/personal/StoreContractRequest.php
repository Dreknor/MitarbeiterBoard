<?php

namespace App\Http\Requests\personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\personal\Employment;
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
            'end'                   => [
                Rule::requiredIf(fn () => in_array($this->input('contract_type'), ['befristet', 'befristet_sachgrund'], true)),
                'nullable', 'date', 'after_or_equal:start',
            ],
            // Nachfolge-Vertrag: beendet die ersetzte Anstellung am Vortag des Beginns.
            // Bei Änderungsvertrag/internem Wechsel Pflicht, sofern die Person noch eine laufende Anstellung hat.
            'replaced_employment_id' => [
                Rule::requiredIf(fn () => $this->mussAnstellungErsetzen()),
                'nullable', 'integer',
                Rule::exists('employments', 'id')->where('employe_id', (int) $this->route('employe'))
                    ->where(fn ($q) => $q->where('status', '!=', EmploymentStatus::Beendet->value)->whereNull('deleted_at')),
            ],
            // Lehrkräfte: Wochenstunden werden aus dem Deputat berechnet (ContractService), sofern nicht manuell gesetzt
            'hours'                 => [
                Rule::requiredIf(fn () => $this->input('employment_type') !== EmploymentType::Lehrer->value || $this->boolean('hours_manual')),
                'nullable', 'numeric', 'min:1', 'max:168',
            ],
            'hours_manual'          => ['nullable', 'boolean'],
            'hour_type_id'          => ['required', 'integer', 'exists:hour_types,id'],
            // Arbeitstage (ISO 1 = Mo … 7 = So) – Grundlage für Soll-Zeit und Urlaubstage
            'workdays'              => ['nullable', 'array', 'min:1'],
            'workdays.*'            => ['integer', 'between:1,7'],
            'department_id'         => ['required', 'integer', 'exists:groups,id'],
            'probation_end'         => ['nullable', 'date', 'after_or_equal:start'],
            'notice_period'         => ['nullable', 'string', 'max:50'],
            'comment'               => ['nullable', 'string', 'max:1000'],
            'is_amendment'          => ['boolean'],
            'amendment_description' => ['nullable', 'string', 'max:255'],
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

    /**
     * Änderungsvertrag/interner Wechsel setzt eine bestehende Anstellung fort – dann muss (beim Anlegen)
     * gewählt werden, welche laufende Anstellung ersetzt wird. Ohne laufende Anstellung (Vorgänger nicht
     * erfasst) bleibt die Angabe optional.
     */
    protected function mussAnstellungErsetzen(): bool
    {
        if (!$this->boolean('is_amendment') && !$this->boolean('is_internal_transfer')) {
            return false;
        }

        return Employment::where('employe_id', (int) $this->route('employe'))
            ->where('status', '!=', EmploymentStatus::Beendet->value)
            ->exists();
    }

    public function messages(): array
    {
        return [
            'replaced_employment_id.required' => 'Bitte wählen Sie bei einem Änderungsvertrag bzw. internen Wechsel die Anstellung, die ersetzt wird.',
            'replaced_employment_id.exists'   => 'Die gewählte Anstellung kann nicht ersetzt werden.',
            'employment_type.required' => 'Bitte wählen Sie einen Anstellungstyp.',
            'employment_type.in'       => 'Ungültiger Anstellungstyp.',
            'contract_type.required'   => 'Bitte wählen Sie einen Vertragstyp.',
            'contract_type.in'         => 'Ungültiger Vertragstyp.',
            'start.required'           => 'Das Startdatum ist erforderlich.',
            'end.after_or_equal'       => 'Das Enddatum muss nach dem Startdatum liegen.',
            'end.required'             => 'Für befristete Verträge ist ein Enddatum erforderlich.',
            'department_id.required'   => 'Bitte wählen Sie einen Bereich.',
            'hour_type_id.required'    => 'Bitte wählen Sie eine Stundenart.',
            'hours.required'           => 'Bitte geben Sie die Wochenstunden an.',
            'hours.min'                => 'Wochenstunden müssen mindestens 1 betragen.',
            'hours.max'                => 'Wochenstunden dürfen maximal 168 betragen.',
            'school_type_id.required'  => 'Für Lehrkräfte ist die Schulart erforderlich.',
            'deputat_hours.required'   => 'Für Lehrkräfte ist das Deputat erforderlich.',
        ];
    }
}

