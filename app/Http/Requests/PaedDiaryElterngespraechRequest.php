<?php

namespace App\Http\Requests;

use App\Models\PaedDiaryElterngespraech;
use App\Models\PaedDiaryElterngespraechVereinbarung;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validierung für Anlegen und Ändern von Elterngesprächen (Web und API v1).
 * Beim Ändern sind alle Felder optional (Autospeichern sendet den ganzen Stand, die App ggf. Teile).
 * Die Berechtigung prüft der Controller über die Policy.
 */
class PaedDiaryElterngespraechRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::regeln($this->isMethod('post'), $this->filled('start_time'));
    }

    /**
     * Gemeinsame Regeln für Web und API v1 (App\Http\Requests\API\v1\ParentMeetingRequest).
     */
    public static function regeln(bool $anlegen, bool $mitBeginn): array
    {
        return [
            'date' => [$anlegen ? 'required' : 'sometimes', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', Rule::when($mitBeginn, 'after:start_time')],
            'location' => ['nullable', 'string', 'max:255'],
            'form' => ['nullable', Rule::in(array_keys(PaedDiaryElterngespraech::FORMEN))],
            'confidential' => ['sometimes', 'boolean'],
            'student_present' => ['sometimes', 'boolean'],
            'parents' => ['nullable', 'string', 'max:1000'],
            'other_participants' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'topics' => ['sometimes', 'nullable', 'array', 'max:50'],
            'topics.*.title' => ['nullable', 'string', 'max:500'],
            'topics.*.preparation' => ['nullable', 'string', 'max:5000'],
            'topics.*.result' => ['nullable', 'string', 'max:10000'],
            'preparation_notes' => ['nullable', 'string', 'max:10000'],
            'preparation_in_protocol' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'next_date' => ['nullable', 'date'],
            'participant_ids' => ['sometimes', 'nullable', 'array', 'max:50'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'agreements' => ['sometimes', 'nullable', 'array', 'max:50'],
            'agreements.*.id' => ['nullable', 'integer'],
            'agreements.*.key' => ['nullable', 'string', 'max:64'],
            'agreements.*.text' => ['nullable', 'string', 'max:2000'],
            'agreements.*.responsible' => ['nullable', Rule::in(array_keys(PaedDiaryElterngespraechVereinbarung::VERANTWORTLICH))],
            'agreements.*.due_date' => ['nullable', 'date'],
            'calendar' => ['sometimes', 'boolean'],
            'pause_entries' => ['sometimes', 'boolean'],
            'version' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Bitte ein Datum angeben.',
            'end_time.after' => 'Das Ende muss nach dem Beginn liegen.',
        ];
    }

    /** Validierte Felder ohne Steuerwerte (version). */
    public function daten(): array
    {
        return collect($this->validated())->except('version')->all();
    }

    public function version(): ?int
    {
        return $this->filled('version') ? (int) $this->input('version') : null;
    }
}
