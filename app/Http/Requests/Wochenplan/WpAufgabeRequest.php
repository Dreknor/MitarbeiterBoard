<?php

namespace App\Http\Requests\Wochenplan;

use Illuminate\Foundation\Http\FormRequest;

class WpAufgabeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create wochenplan');
    }

    public function rules(): array
    {
        $aendern = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'aufgabe' => [$aendern ? 'sometimes' : 'required', 'string', 'max:2000'],
            'dauer' => ['nullable', 'string', 'max:50'],
            'niveau' => ['nullable', 'integer', 'in:1,2,3'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            // freiwillige Aufgabe (im PDF mit *) bzw. Empfehlung für die Freiarbeit (eigener Abschnitt)
            'freiwillig' => ['sometimes', 'boolean'],
            'freiarbeit' => ['sometimes', 'boolean'],
            // Text vor der Bearbeitung – Konfliktprüfung, wenn jemand anderes die Aufgabe geändert hat
            'basis' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['link.url' => 'Bitte einen gültigen Link (http/https) angeben.'];
    }
}
