<?php

namespace App\Http\Requests\Personal;

use App\Enums\TrainingStatus;
use Illuminate\Validation\Rule;

/**
 * FormRequest für das Aktualisieren von Fortbildungen.
 * Erweitert StoreTrainingRequest um Status-Validierung.
 */
class UpdateTrainingRequest extends StoreTrainingRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['required', Rule::in(array_column(TrainingStatus::cases(), 'value'))],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'status.required' => 'Bitte wählen Sie einen Status.',
            'status.in'       => 'Ungültiger Fortbildungsstatus.',
        ]);
    }
}

