<?php

namespace App\Http\Requests\API\v1;

class UpdatePaedDiaryEntryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'schueler_ids' => ['sometimes', 'array', 'min:1', 'max:200'],
            'schueler_ids.*' => ['required', 'integer', 'distinct', $this->existingSchueler()],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:paed_diary_categories,id'],
            'entry_date' => ['sometimes', 'date_format:Y-m-d'],
            'content' => ['sometimes', 'string', 'min:1', 'max:20000'],
            'is_dossier_only' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
            // Konfliktschutz: updated_at, das der Client zuletzt gelesen hat (weicht es ab → 409)
            'expected_updated_at' => ['sometimes', 'date'],
        ];
    }
}
