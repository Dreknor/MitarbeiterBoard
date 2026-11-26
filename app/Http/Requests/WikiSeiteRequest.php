<?php

namespace App\Http\Requests;

use App\Services\Wiki\WikiService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anlegen/Speichern einer Wiki-Seite (Rechte prüft der Controller über die WikiSeitePolicy).
 */
class WikiSeiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titel' => ['required', 'string', 'max:150'],
            'inhalt' => ['nullable', 'string', 'max:16000000'],
            'parent_id' => ['nullable', 'integer', 'exists:wiki_seiten,id'],
            'notiz' => ['nullable', 'string', 'max:250'],
            'tags' => ['nullable', 'array', 'max:'.WikiService::MAX_TAGS],
            'tags.*' => ['string', 'max:40'],
            'version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return ['titel' => 'Titel', 'inhalt' => 'Inhalt', 'parent_id' => 'übergeordnete Seite', 'notiz' => 'Änderungsnotiz'];
    }

    /**
     * @return array{titel: string, inhalt: ?string, parent_id: ?int, notiz: ?string, tags: array}
     */
    public function daten(): array
    {
        return [
            'titel' => (string) $this->input('titel'),
            'inhalt' => $this->input('inhalt'),
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
            'notiz' => $this->input('notiz'),
            'tags' => array_values((array) $this->input('tags', [])),
        ];
    }
}
