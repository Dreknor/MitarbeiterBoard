@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    Eigene Daten
@endsection

@section('content')
@php
    // Auswahlfelder: alter Formularwert (nach Validierungsfehler) vor gespeichertem Wert
    $ja = fn (string $feld, $gespeichert) => (string) old($feld, (int) (bool) $gespeichert);
    $geschlecht = old('geschlecht', $data->geschlecht);
@endphp
<div class="personal-wrapper" x-data="{ fotoForm: false }">

    {{-- Kopf --}}
    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
        <div class="flex items-center gap-4">
            <img src="{{ $employe->photo() }}" alt="" class="w-16 h-16 rounded-full object-cover ring-2 ring-white shadow">
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $employe->name }}</h1>
                <p class="text-sm text-gray-500">{{ $employe->email }}</p>
                <button type="button" class="text-sm text-blue-600 hover:text-blue-700 mt-1" @click="fotoForm = !fotoForm"
                        x-text="fotoForm ? 'Abbrechen' : 'Foto ändern'">Foto ändern</button>
            </div>
        </div>
        @if(Route::has('self-service.index'))
            <a href="{{ route('self-service.index') }}" class="btn-personal-secondary text-sm">Mein Profil (Verträge, Dokumente …)</a>
        @endif
    </div>

    <form action="{{ route('employes.self.photo') }}" method="post" enctype="multipart/form-data"
          class="personal-card mb-6 flex flex-wrap items-end gap-3" x-show="fotoForm" x-cloak>
        @csrf
        <div class="flex-1 min-w-60">
            <label class="personal-label" for="foto">Neues Foto (JPG, PNG oder GIF, max. {{ $maxFotoKb / 1024 }} MB)</label>
            <input id="foto" type="file" name="file" accept=".jpg,.jpeg,.png,.gif" required class="personal-input">
        </div>
        <button type="submit" class="btn-personal-primary">Foto hochladen</button>
    </form>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Profil bearbeiten --}}
        <form action="{{ route('employes.self.update') }}" method="post" class="personal-card xl:col-span-2">
            @csrf
            @method('PUT')

            <h2 class="text-base font-semibold text-gray-900 mb-4">Persönliche Daten</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="personal-label" for="vorname">Vorname *</label>
                    <input id="vorname" type="text" name="vorname" class="personal-input" required
                           value="{{ old('vorname', $data->vorname) }}">
                </div>
                <div>
                    <label class="personal-label" for="familienname">Familienname *</label>
                    <input id="familienname" type="text" name="familienname" class="personal-input" required
                           value="{{ old('familienname', $data->familienname) }}">
                </div>
                <div>
                    <label class="personal-label" for="geburtstag">Geburtsdatum *</label>
                    <input id="geburtstag" type="date" name="geburtstag" class="personal-input" required
                           value="{{ old('geburtstag', $data->geburtstag?->format('Y-m-d')) }}">
                </div>
                <div>
                    <label class="personal-label" for="geschlecht">Geschlecht *</label>
                    <select id="geschlecht" name="geschlecht" class="personal-input" required>
                        <option value="" disabled @selected(!$geschlecht)>— wählen —</option>
                        @foreach(['weiblich', 'männlich', 'anderes'] as $g)
                            <option value="{{ $g }}" @selected($geschlecht === $g)>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="personal-label" for="geburtsname">Geburtsname</label>
                    <input id="geburtsname" type="text" name="geburtsname" class="personal-input"
                           value="{{ old('geburtsname', $data->geburtsname) }}">
                </div>
                <div>
                    <label class="personal-label" for="geburtsort">Geburtsort</label>
                    <input id="geburtsort" type="text" name="geburtsort" class="personal-input"
                           value="{{ old('geburtsort', $data->geburtsort) }}">
                </div>
                <div>
                    <label class="personal-label" for="staatsangehoerigkeit">Staatsangehörigkeit</label>
                    <input id="staatsangehoerigkeit" type="text" name="staatsangehoerigkeit" class="personal-input"
                           value="{{ old('staatsangehoerigkeit', $data->staatsangehoerigkeit) }}">
                </div>
                <div>
                    <label class="personal-label" for="sozialversicherungsnummer">Sozialversicherungsnummer</label>
                    <input id="sozialversicherungsnummer" type="text" name="sozialversicherungsnummer" class="personal-input" autocomplete="off"
                           value="{{ old('sozialversicherungsnummer', $data->sozialversicherungsnummer) }}">
                </div>
                <div>
                    <label class="personal-label" for="schwerbehindert">Schwerbehindert</label>
                    <select id="schwerbehindert" name="schwerbehindert" class="personal-input">
                        <option value="0" @selected($ja('schwerbehindert', $data->schwerbehindert) === '0')>nein</option>
                        <option value="1" @selected($ja('schwerbehindert', $data->schwerbehindert) === '1')>ja</option>
                    </select>
                </div>
            </div>

            <h2 class="text-base font-semibold text-gray-900 mt-8 mb-4">Benachrichtigungen</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="personal-label" for="send_mail_if_absence">E-Mail bei Abwesenheit oder Urlaub</label>
                    <select id="send_mail_if_absence" name="send_mail_if_absence" class="personal-input">
                        <option value="0" @selected($ja('send_mail_if_absence', $employe->send_mails_if_absence) === '0')>nein</option>
                        <option value="1" @selected($ja('send_mail_if_absence', $employe->send_mails_if_absence) === '1')>ja</option>
                    </select>
                </div>
            </div>

            <h2 class="text-base font-semibold text-gray-900 mt-8 mb-4" id="atom-feed-settings">Veranstaltungs-Feed</h2>
            <div>
                <label class="personal-label" for="atom_feed_url">ATOM-Feed-URL</label>
                <input id="atom_feed_url" type="url" name="atom_feed_url" class="personal-input"
                       placeholder="https://veranstaltungen.hauptfach-mensch.de/feed/atom.xml"
                       value="{{ old('atom_feed_url', $employe->atom_feed_url) }}">
                <p class="text-xs text-gray-500 mt-1">Leer lassen, um den Standard-Feed zu verwenden.</p>
            </div>

            @can('has timesheet')
                <h2 class="text-base font-semibold text-gray-900 mt-8 mb-1">Google-Kalender</h2>
                <p class="text-sm text-gray-500 mb-4">
                    Mit der Kalender-ID (Einstellungen deines Google-Kalenders) werden deine Arbeitszeiten in deinen Google-Kalender eingetragen.
                    Dienstplan-Termine kannst du unter „Mein Dienstplan“ als Kalender abonnieren.
                </p>
                <div>
                    <label class="personal-label" for="google_calendar_link">Google-Kalender-ID</label>
                    <input id="google_calendar_link" type="text" name="google_calendar_link" class="personal-input" autocomplete="off"
                           value="{{ old('google_calendar_link', $data->google_calendar_link) }}">
                </div>
            @endcan

            <div class="flex justify-end mt-6 pt-6 border-t border-gray-100">
                <button type="submit" class="btn-personal-primary">Speichern</button>
            </div>
        </form>

        {{-- Rechte Spalte --}}
        <div class="space-y-6">
            @if($employments->isNotEmpty())
            <div class="personal-card">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Arbeitsdaten</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Beschäftigt seit</dt><dd class="font-medium">{{ $firstStart ? \Carbon\Carbon::parse($firstStart)->format('d.m.Y') : '–' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Stellenanteil</dt><dd class="font-medium">{{ round($employments->sum(fn ($e) => $e->percent), 1) }} % · {{ round($employments->sum('hours'), 2) }} Std.</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Urlaubsanspruch</dt><dd class="font-medium">{{ $holidayClaim }} Tage</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Stundenkonto</dt><dd class="font-medium">{{ convertTime($employe->timesheet_latest?->working_time_account) }} h</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Zeiterfassung Key</dt><dd class="font-medium">{{ $data->time_recording_key ?: '–' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Zeiterfassung PIN</dt><dd class="font-medium">{{ $data->hasPin() ? 'gesetzt' : 'nicht gesetzt' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Monatliche Mail Arbeitszeit</dt><dd class="font-medium">{{ $data->mail_timesheet ? 'ja' : 'nein' }}</dd></div>
                </dl>

                <h3 class="text-sm font-semibold text-gray-700 mt-5 mb-2">Laufende Anstellungen</h3>
                <ul class="space-y-2 text-sm">
                    @foreach($employments as $employment)
                        <li class="flex justify-between gap-3">
                            <span class="font-medium text-gray-800">{{ $employment->department?->name ?? '–' }}</span>
                            <span class="text-gray-500 text-right">
                                {{ $employment->hours }} Std. ({{ round($employment->percent, 1) }} %)<br>
                                <span class="text-xs">seit {{ $employment->start->format('d.m.Y') }}@if($employment->end), bis {{ $employment->end->format('d.m.Y') }}@endif</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if($groups->isNotEmpty())
            <div class="personal-card">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Gruppen</h2>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($groups as $group)
                        <a href="{{ url($group->name . '/themes') }}" class="badge-gray hover:bg-gray-200">{{ $group->name }}</a>
                    @endforeach
                </div>
            </div>
            @endif

            @can('view paed diary')
                @include('personal.employes._paed_app_card')
            @endcan
        </div>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush
