<?php

namespace App\Http\Requests;

use App\Models\TagesvorschauEinstellung;
use App\Services\Benachrichtigungen\TagesvorschauService;
use App\Services\OxCalendarService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BenachrichtigungEinstellungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();
        $bereiche = app(TagesvorschauService::class)->sichtbareQuellen($user)->map->bereich()->all();
        $kalender = app(OxCalendarService::class)->sichtbareKalender($user)->pluck('id')->all();

        return [
            'kategorien'                          => ['nullable', 'array'],
            'kategorien.*.push'                   => ['nullable', 'boolean'],
            'kategorien.*.mail'                   => ['required', Rule::in(['sofort', 'zusammenfassung', 'aus'])],

            'tagesvorschau'                       => ['required', 'array'],
            'tagesvorschau.per_mail'              => ['nullable', 'boolean'],
            'tagesvorschau.per_push'              => ['nullable', 'boolean'],
            'tagesvorschau.zeitpunkt'             => ['required', Rule::in([TagesvorschauEinstellung::MORGENS, TagesvorschauEinstellung::VORABEND])],
            'tagesvorschau.uhrzeit'               => ['required', 'date_format:H:i'],
            'tagesvorschau.bereiche'              => ['nullable', 'array'],
            'tagesvorschau.bereiche.*'            => ['string', Rule::in($bereiche)],
            'tagesvorschau.kalender_ids'          => ['nullable', 'array'],
            'tagesvorschau.kalender_ids.*'        => ['integer', Rule::in($kalender)],
            'tagesvorschau.eingeladene_termine'   => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $zeitpunkt = $this->input('tagesvorschau.zeitpunkt');
                $uhrzeit = (string) $this->input('tagesvorschau.uhrzeit');
                $fenster = config("benachrichtigungen.tagesvorschau.fenster.$zeitpunkt");

                if (!$fenster || !preg_match('/^\d{2}:\d{2}$/', $uhrzeit)) {
                    return;
                }

                [$von, $bis] = $fenster;

                if ($uhrzeit < $von || $uhrzeit > $bis) {
                    $validator->errors()->add(
                        'tagesvorschau.uhrzeit',
                        "Die Uhrzeit muss zwischen $von und $bis Uhr liegen."
                    );
                }

                if ((int) substr($uhrzeit, 3, 2) % 15 !== 0) {
                    $validator->errors()->add('tagesvorschau.uhrzeit', 'Bitte eine Uhrzeit im Viertelstunden-Raster wählen.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'tagesvorschau.uhrzeit'        => 'Uhrzeit',
            'tagesvorschau.zeitpunkt'      => 'Zeitpunkt',
            'tagesvorschau.bereiche.*'     => 'Bereich',
            'tagesvorschau.kalender_ids.*' => 'Kalender',
        ];
    }
}
