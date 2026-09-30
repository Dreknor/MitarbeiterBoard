@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $employe->vorname }} {{ $employe->familienname }} – Stammdaten
@endsection

@section('title')
    Personalverwaltung
@endsection

@section('content')
@php
    // Auswahlfelder: alter Formularwert (nach Validierungsfehler) vor gespeichertem Wert
    $ja = fn (string $feld, $gespeichert) => (string) old($feld, (int) (bool) $gespeichert);
@endphp
<div class="personal-wrapper">

    @include('personal.partials._akte_header', ['active' => 'stammdaten'])

    @unless($data->exists)
        <div class="alert-info text-sm">
            Für diese Person sind noch keine Stammdaten gespeichert. Die Felder sind aus dem Benutzerkonto vorbelegt –
            der Datensatz wird erst beim Speichern angelegt.
        </div>
    @endunless

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Persönliche Daten & Einstellungen --}}
        <form action="{{ route('employes.update', $employe->id) }}" method="post" class="personal-card xl:col-span-2">
            @csrf
            @method('put')

            <h2 class="text-base font-semibold text-gray-900 mb-4">Persönliche Daten</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="personal-label" for="familienname">Familienname *</label>
                    <input id="familienname" type="text" class="personal-input" name="familienname" required autocomplete="off"
                           value="{{ old('familienname', $data->familienname) }}">
                </div>
                <div>
                    <label class="personal-label" for="vorname">Vorname *</label>
                    <input id="vorname" type="text" class="personal-input" name="vorname" required autocomplete="off"
                           value="{{ old('vorname', $data->vorname) }}">
                </div>
                <div>
                    <label class="personal-label" for="geburtstag">Geburtsdatum *</label>
                    <input id="geburtstag" type="date" class="personal-input" name="geburtstag" required
                           value="{{ old('geburtstag', $data->geburtstag?->format('Y-m-d')) }}">
                </div>
                <div>
                    <label class="personal-label" for="geschlecht">Geschlecht *</label>
                    @php $geschlecht = old('geschlecht', $data->geschlecht); @endphp
                    <select id="geschlecht" name="geschlecht" class="personal-input" required>
                        <option value="" disabled @selected(!$geschlecht)>— wählen —</option>
                        @foreach(['weiblich', 'männlich', 'anderes'] as $g)
                            <option value="{{ $g }}" @selected($geschlecht === $g)>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="personal-label" for="geburtsname">Geburtsname</label>
                    <input id="geburtsname" type="text" class="personal-input" name="geburtsname" autocomplete="off"
                           value="{{ old('geburtsname', $data->geburtsname) }}">
                </div>
                <div>
                    <label class="personal-label" for="geburtsort">Geburtsort</label>
                    <input id="geburtsort" type="text" class="personal-input" name="geburtsort" autocomplete="off"
                           value="{{ old('geburtsort', $data->geburtsort) }}">
                </div>
                <div>
                    <label class="personal-label" for="staatsangehoerigkeit">Staatsangehörigkeit *</label>
                    <input id="staatsangehoerigkeit" type="text" class="personal-input" name="staatsangehoerigkeit" required autocomplete="off"
                           value="{{ old('staatsangehoerigkeit', $data->staatsangehoerigkeit ?? 'deutsch') }}">
                </div>
                <div>
                    <label class="personal-label" for="sozialversicherungsnummer">Sozialversicherungsnummer</label>
                    <input id="sozialversicherungsnummer" type="text" class="personal-input" name="sozialversicherungsnummer" autocomplete="off"
                           value="{{ old('sozialversicherungsnummer', $data->sozialversicherungsnummer) }}">
                </div>
                <div>
                    <label class="personal-label" for="schwerbehindert">Schwerbehindert *</label>
                    <select id="schwerbehindert" name="schwerbehindert" class="personal-input" required>
                        <option value="0" @selected($ja('schwerbehindert', $data->schwerbehindert) === '0')>nein</option>
                        <option value="1" @selected($ja('schwerbehindert', $data->schwerbehindert) === '1')>ja</option>
                    </select>
                </div>
            </div>

            <h2 class="text-base font-semibold text-gray-900 mt-8 mb-4">Benachrichtigungen & Kalender</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="personal-label" for="send_mail_if_absence">E-Mail bei Abwesenheit/Urlaub</label>
                    <select id="send_mail_if_absence" name="send_mail_if_absence" class="personal-input" required>
                        <option value="0" @selected($ja('send_mail_if_absence', $employe->send_mails_if_absence) === '0')>nein</option>
                        <option value="1" @selected($ja('send_mail_if_absence', $employe->send_mails_if_absence) === '1')>ja</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="personal-label" for="google_calendar_link">Google-Kalender-ID (Arbeitszeiten)</label>
                    <input id="google_calendar_link" type="text" class="personal-input" name="google_calendar_link" autocomplete="off"
                           value="{{ old('google_calendar_link', $data->google_calendar_link) }}">
                </div>
                <div class="md:col-span-3 text-sm">
                    <span class="text-gray-500">Dienstplan-Kalenderabo:</span>
                    @if($employe->roster_feed_token)
                        <span class="badge-green">eingerichtet</span>
                    @else
                        <span class="text-gray-400">nicht eingerichtet</span>
                    @endif
                    <span class="text-xs text-gray-400">(richtet die Person selbst unter „Mein Dienstplan“ ein)</span>
                </div>
            </div>

            <div class="flex justify-end mt-6 pt-6 border-t border-gray-100">
                <button type="submit" class="btn-personal-primary">Stammdaten speichern</button>
            </div>
        </form>

        {{-- Arbeitsdaten --}}
        <div class="space-y-6">
            <div class="personal-card">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Überblick</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Beschäftigt seit</dt>
                        <dd class="font-medium">{{ $firstStart ? \Carbon\Carbon::parse($firstStart)->format('d.m.Y') : '–' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Urlaubsanspruch</dt>
                        <dd class="font-medium">{{ $holidayClaim }} Tage</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Resturlaub {{ now()->year }}</dt>
                        <dd class="font-medium">{{ $holidayRest }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Stundenkonto</dt>
                        <dd class="font-medium">{{ convertTime($workingTimeAccount) }} h</dd>
                    </div>
                </dl>
                <div class="flex flex-wrap gap-2 mt-4">
                    @canany(['has holidays', 'approve holidays'])
                        <a href="{{ route('holidays.account', $employe->id) }}" class="btn-personal-secondary text-xs">Urlaubskonto</a>
                    @endcanany
                    @can('viewEmploye', [\App\Models\personal\Timesheet::class, $employe])
                        <a href="{{ route('timesheets.show', $employe->id) }}" class="btn-personal-secondary text-xs">Arbeitszeitnachweis</a>
                        <form action="{{ route('timesheets.recalculate-all', $employe->id) }}" method="post">
                            @csrf
                            <button type="submit" class="btn-personal-secondary text-xs">Stundenkonto neu berechnen</button>
                        </form>
                    @endcan
                </div>
            </div>

            @php $anschrift = $employe->address; @endphp
            <form method="post" action="{{ route('employes.address.update', $employe->id) }}" class="personal-card">
                @csrf
                @method('put')
                <h2 class="text-base font-semibold text-gray-900 mb-4">Anschrift</h2>
                <div class="grid grid-cols-4 gap-3">
                    <div class="col-span-3">
                        <label class="personal-label" for="strasse">Straße</label>
                        <input id="strasse" name="strasse" type="text" class="personal-input" autocomplete="off"
                               value="{{ old('strasse', $anschrift?->strasse) }}">
                    </div>
                    <div>
                        <label class="personal-label" for="nr">Nr.</label>
                        <input id="nr" name="nr" type="text" class="personal-input" autocomplete="off"
                               value="{{ old('nr', $anschrift?->nr) }}">
                    </div>
                    <div>
                        <label class="personal-label" for="plz">PLZ</label>
                        <input id="plz" name="plz" type="text" class="personal-input" autocomplete="off"
                               value="{{ old('plz', $anschrift?->plz) }}">
                    </div>
                    <div class="col-span-3">
                        <label class="personal-label" for="ort">Ort</label>
                        <input id="ort" name="ort" type="text" class="personal-input" autocomplete="off"
                               value="{{ old('ort', $anschrift?->ort) }}">
                    </div>
                    <div class="col-span-4">
                        <label class="personal-label" for="land">Land</label>
                        <input id="land" name="land" type="text" class="personal-input" autocomplete="off"
                               value="{{ old('land', $anschrift?->land ?? 'Deutschland') }}">
                    </div>
                </div>
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-100">
                    <button type="submit" class="btn-personal-primary">Anschrift speichern</button>
                </div>
            </form>

            <form method="post" action="{{ route('employes.data.update', $employe->id) }}" class="personal-card">
                @csrf
                @method('put')
                <h2 class="text-base font-semibold text-gray-900 mb-4">Urlaub & Zeiterfassung</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="personal-label" for="holidayClaim">Urlaubsanspruch *</label>
                            <input id="holidayClaim" name="holidayClaim" type="number" min="1" required class="personal-input"
                                   value="{{ old('holidayClaim', $holidayClaim) }}">
                        </div>
                        <div>
                            <label class="personal-label" for="date_start">gültig ab *</label>
                            <input id="date_start" name="date_start" type="date" required class="personal-input"
                                   value="{{ old('date_start', now()->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 -mt-2">Eine Änderung wird als neuer Eintrag ab dem Datum gespeichert; frühere Ansprüche bleiben erhalten.</p>
                    <div>
                        <label class="personal-label" for="time_recording_key">Zeiterfassung: Key-Nummer</label>
                        <input id="time_recording_key" name="time_recording_key" type="text" inputmode="numeric" class="personal-input"
                               value="{{ old('time_recording_key', $data->time_recording_key) }}">
                    </div>
                    <div>
                        <label class="personal-label" for="secret_key">Zeiterfassung: PIN</label>
                        <input id="secret_key" name="secret_key" type="password" inputmode="numeric" autocomplete="new-password" class="personal-input"
                               placeholder="{{ $data->hasPin() ? 'gesetzt – leer lassen für unverändert' : 'nicht gesetzt' }}">
                    </div>
                    <div>
                        <label class="personal-label" for="mail_timesheet">Monatliche Mail zum Arbeitszeitnachweis</label>
                        <select id="mail_timesheet" name="mail_timesheet" class="personal-input">
                            <option value="0" @selected($ja('mail_timesheet', $data->mail_timesheet) === '0')>nein</option>
                            <option value="1" @selected($ja('mail_timesheet', $data->mail_timesheet) === '1')>ja</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-100">
                    <button type="submit" class="btn-personal-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
