<?php

namespace App\Http\Requests\Personal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest für das Anlegen/Aktualisieren von Qualifikationen.
 */
class StoreQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qualification_type_id' => ['required', 'exists:pers_qualification_types,id'],
            'acquired_date'         => ['required', 'date'],
            'expiry_date'           => ['nullable', 'date', 'after:acquired_date'],
            'document_id'           => ['nullable', 'exists:pers_documents,id'],
            'notes'                 => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'qualification_type_id.required' => 'Bitte wählen Sie einen Qualifikationstyp.',
            'acquired_date.required'         => 'Das Erwerbsdatum ist erforderlich.',
            'expiry_date.after'              => 'Das Ablaufdatum muss nach dem Erwerbsdatum liegen.',
            'notes.max'                      => 'Die Anmerkungen dürfen maximal 2000 Zeichen lang sein.',
        ];
    }
}

