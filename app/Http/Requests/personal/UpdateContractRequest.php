<?php

namespace App\Http\Requests\Personal;

/**
 * FormRequest für das Aktualisieren von Anstellungen.
 * Erbt alle Regeln von StoreContractRequest (identische Validierung).
 */
class UpdateContractRequest extends StoreContractRequest
{
    // Gleiche Regeln wie Store – employment_type Fallback via Request-Input
}

