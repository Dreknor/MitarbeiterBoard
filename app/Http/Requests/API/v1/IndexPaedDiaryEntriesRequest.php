<?php

namespace App\Http\Requests\API\v1;

class IndexPaedDiaryEntriesRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'category_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            // Delta-Abfrage: nur seit diesem Zeitpunkt geänderte Einträge (ISO-8601)
            'updated_since' => ['sometimes', 'date'],
            // Volltextsuche (Eintragstext und Kategorie; alle Suchwörter müssen vorkommen)
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            // Nur Klassen-Feed: eigene („own“) bzw. Einträge von Kolleg*innen („others“)
            'author' => ['sometimes', 'in:all,own,others'],
        ];
    }
}
