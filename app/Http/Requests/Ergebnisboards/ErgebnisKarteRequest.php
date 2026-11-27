<?php

namespace App\Http\Requests\Ergebnisboards;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Karte anlegen/ändern. Ob der Bereich existiert und die Zuständigen Zugriff haben,
 * prüft ErgebnisboardService::karteSpeichern().
 */
class ErgebnisKarteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pflicht = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'bereich' => [$pflicht, 'string', 'max:64'],
            'titel' => [$pflicht, 'string', 'max:255'],
            'zusatz' => ['nullable', 'string', 'max:2000'],
            'beschreibung' => ['nullable', 'string', 'max:50000'],
            'checkliste' => ['sometimes', 'nullable', 'array', 'max:50'],
            'checkliste.*.id' => ['nullable', 'string', 'max:20'],
            'checkliste.*.text' => ['nullable', 'string', 'max:255'],
            'checkliste.*.erledigt' => ['sometimes', 'boolean'],
            'etikett' => ['nullable', 'string', 'max:60'],
            'fokus' => ['sometimes', 'boolean'],
            'breit' => ['sometimes', 'boolean'],
            'faellig_am' => ['nullable', 'date'],
            'zustaendige' => ['sometimes', 'array', 'max:50'],
            'zustaendige.*' => ['integer'],
            'version' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'titel' => 'Titel', 'bereich' => 'Feld', 'faellig_am' => 'Fälligkeit', 'zusatz' => 'Zusatz',
            'beschreibung' => 'Beschreibung', 'checkliste' => 'Checkliste', 'checkliste.*.text' => 'Checklistenpunkt',
        ];
    }

    public function daten(): array
    {
        return collect($this->validated())->except('version')->all();
    }

    public function version(): ?int
    {
        return $this->filled('version') ? (int) $this->input('version') : null;
    }
}
