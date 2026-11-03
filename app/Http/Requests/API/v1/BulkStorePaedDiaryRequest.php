<?php

namespace App\Http\Requests\API\v1;

class BulkStorePaedDiaryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'schueler_ids' => ['required', 'array', 'min:1', 'max:200'],
            'schueler_ids.*' => ['required', 'integer', 'distinct', $this->existingSchueler()],
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
            'schueler_ids' => 'Schüler',
            'schueler_ids.*' => 'Schüler',
            'category_id' => 'Kategorie',
            'entry_date' => 'Datum',
            'content' => 'Inhalt',
        ];
    }
}
