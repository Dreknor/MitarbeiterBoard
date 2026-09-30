<?php

namespace App\Http\Requests\personal;

/**
 * FormRequest für das Aktualisieren von Anstellungen.
 * Erbt alle Regeln von StoreContractRequest (identische Validierung).
 */
class UpdateContractRequest extends StoreContractRequest
{
    // Gleiche Regeln wie Store – employment_type Fallback via Request-Input.
    // Die ersetzte Anstellung wird nur beim Anlegen gewählt (ContractService::update ignoriert sie).
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['replaced_employment_id']);

        return $rules;
    }
}

