<?php

namespace App\Http\Requests\Ergebnisboards;

use App\Support\ErgebnisboardVorlagen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anlegen (POST, mit Vorlage + Zugriffen) und Kopf ändern (PUT, Teilfelder) eines Ergebnisboards.
 */
class ErgebnisboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $neu = $this->isMethod('post');
        $pflicht = $neu ? 'required' : 'sometimes';

        return [
            'titel' => [$pflicht, 'string', 'max:255'],
            'untertitel' => ['nullable', 'string', 'max:500'],
            'kopfzeile' => ['nullable', 'string', 'max:255'],
            'fusszeile' => ['nullable', 'string', 'max:255'],
            'vorlage' => [$neu ? 'required' : 'prohibited', 'string', Rule::in(array_keys(ErgebnisboardVorlagen::alle()))],
            'farbschema' => ['sometimes', Rule::in(array_keys(ErgebnisboardVorlagen::FARBSCHEMATA))],
            'meeting_id' => ['nullable', 'integer', 'exists:meetings,id'],
            'meeting_lesen' => ['sometimes', 'boolean'],
            'theme_id' => ['nullable', 'integer', 'exists:themes,id'],
            'achsen' => ['sometimes', 'array'],
            'legende' => ['sometimes', 'array'],
            'zugriffe' => ['sometimes', 'array', 'max:200'],
            'zugriffe.*.typ' => ['required', Rule::in(['user', 'group', 'role'])],
            'zugriffe.*.id' => ['required', 'integer', 'min:1'],
            'zugriffe.*.recht' => ['required', Rule::in(['lesen', 'schreiben', 'verwalten'])],
            'version' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return ['titel' => 'Titel', 'vorlage' => 'Vorlage', 'meeting_id' => 'Sitzung', 'theme_id' => 'Thema'];
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
