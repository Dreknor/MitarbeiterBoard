<?php

namespace App\Http\Requests\API\v1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Basisklasse für alle Requests der API v1.
 * Die fachliche Autorisierung erfolgt über Policies in den Controllern.
 * Validierungsfehler werden immer als JSON (422) geliefert.
 */
abstract class ApiRequest extends FormRequest
{
    /** Erlaubte Werte für boolesche Query-Parameter (?flag=true) */
    public const BOOLEAN_QUERY = 'in:true,false,1,0,yes,no,on,off';

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Die übermittelten Daten sind ungültig.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /** Regel: existierender, nicht gelöschter Schüler */
    protected function existingSchueler(): string
    {
        return 'exists:schueler,id,deleted_at,NULL';
    }
}
