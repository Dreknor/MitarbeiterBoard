<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOxTerminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit calendar events');
    }

    public function rules(): array
    {
        return [
            'ox_calendar_id'      => ['required', 'exists:ox_calendars,id'],
            'titel'               => ['required', 'string', 'max:255'],
            'beschreibung'        => ['nullable', 'string', 'max:5000'],
            'ort'                 => ['nullable', 'string', 'max:255'],
            'beginn'              => ['required', 'date'],
            'ende'                => ['required', 'date', 'after:beginn'],
            'ganztaegig'          => ['boolean'],
            'rrule'               => ['nullable', 'string', 'max:500'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'titel.required'               => 'Bitte geben Sie einen Titel ein.',
            'titel.max'                    => 'Der Titel darf maximal 255 Zeichen lang sein.',
            'beginn.required'              => 'Bitte wählen Sie einen Startzeitpunkt.',
            'ende.required'                => 'Bitte wählen Sie einen Endzeitpunkt.',
            'ende.after'                   => 'Das Ende muss nach dem Beginn liegen.',
            'ox_calendar_id.exists'        => 'Der gewählte Kalender existiert nicht.',
            'expected_updated_at.required' => 'Optimistic-Locking-Feld fehlt.',
        ];
    }
}

