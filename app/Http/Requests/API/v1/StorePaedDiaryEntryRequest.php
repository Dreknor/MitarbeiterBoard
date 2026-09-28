<?php

namespace App\Http\Requests\API\v1;

class StorePaedDiaryEntryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'schueler_id' => ['required', 'integer', $this->existingSchueler()],
            'category_id' => ['nullable', 'integer', 'exists:paed_diary_categories,id'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'content' => ['required', 'string', 'max:20000'],
            'is_dossier_only' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'schueler_id' => 'Schüler',
            'category_id' => 'Kategorie',
            'entry_date' => 'Datum',
            'content' => 'Inhalt',
        ];
    }
}
