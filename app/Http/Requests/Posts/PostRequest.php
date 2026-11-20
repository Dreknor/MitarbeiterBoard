<?php

namespace App\Http\Requests\Posts;

use App\Services\Posts\PostService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nachricht anlegen/bearbeiten. Rechte: PostService::darfPosten() (neu) bzw. PostPolicy::update.
 * Erlaubte Zielgruppen prüft PostService::speichern().
 */
class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post
            ? $this->user()->can('update', $post)
            : app(PostService::class)->darfPosten($this->user());
    }

    public function rules(): array
    {
        // Unveränderte (inzwischen vergangene) Frist beim Bearbeiten nicht erneut prüfen
        $post = $this->route('post');
        $fristUnveraendert = $post && $post->bestaetigung_frist?->toDateString() === $this->input('bestaetigung_frist');

        return [
            'header' => ['required', 'string', 'max:255'],
            'kurzfassung' => ['nullable', 'string', 'max:500'],
            'text' => ['nullable', 'string'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'released' => ['sometimes', 'boolean'],
            'auf_dashboard' => ['sometimes', 'boolean'],
            'sichtbar_bis' => ['nullable', 'date'],
            'bestaetigung_erforderlich' => ['sometimes', 'boolean'],
            'bestaetigung_frist' => $fristUnveraendert ? ['nullable', 'date'] : ['nullable', 'date', 'after_or_equal:today'],
            'bestaetigungen_zuruecksetzen' => ['sometimes', 'boolean'],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:'.((int) config('app.maxFileSize', 100000))],
        ];
    }

    public function messages(): array
    {
        return [
            'header.required' => 'Bitte eine Überschrift angeben.',
            'groups.required' => 'Bitte mindestens eine Gruppe wählen.',
            'bestaetigung_frist.after_or_equal' => 'Die Frist darf nicht in der Vergangenheit liegen.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function daten(): array
    {
        $daten = $this->safe()->except(['files', 'bestaetigungen_zuruecksetzen']);
        $daten['auf_dashboard'] = $this->boolean('auf_dashboard', ! $this->has('_formular'));
        $daten['bestaetigung_erforderlich'] = $this->boolean('bestaetigung_erforderlich');
        $daten['released'] = $this->boolean('released');

        return $daten;
    }
}
