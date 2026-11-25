@extends('layouts.app')

@push('css')
    @vite(['resources/css/ergebnisboards.css', 'resources/js/ergebnisboards.js'])
@endpush

@php
    $meetingListe = collect($meetings);
    if ($meeting && ! $meetingListe->contains('id', $meeting->id)) {
        $meetingListe->prepend(['id' => $meeting->id, 'titel' => app(\App\Services\Ergebnisboards\ErgebnisboardService::class)->meetingTitel($meeting)]);
    }
    $themenListe = collect($themen);
    if ($theme && ! $themenListe->contains('id', $theme->id)) {
        $themenListe->prepend(['id' => $theme->id, 'titel' => $theme->theme]);
    }
    $vorlageGewaehlt = old('vorlage', array_key_exists($gewaehlt, $vorlagen) ? $gewaehlt : array_key_first($vorlagen));
    $meetingGewaehlt = (string) old('meeting_id', $meeting?->id);
    $themeGewaehlt = (string) old('theme_id', $theme?->id);
    $alteZugriffe = collect(old('zugriffe', $auswahl))->map(fn ($z) => ['typ' => $z['typ'] ?? 'user', 'id' => (int) ($z['id'] ?? 0), 'recht' => $z['recht'] ?? 'lesen'])->values()->all();
@endphp

@section('content')
<div class="erg-wrapper erg-schmal">
    <a href="{{ route('ergebnisboards.index') }}" class="erg-back mb-3"><i class="fas fa-arrow-left"></i> Boards</a>
    <h1 class="erg-page-title">Neues Board</h1>
    <p class="erg-page-sub mb-5">Vorlage wählen, Titel vergeben – Karten legen Sie danach direkt im Board an.</p>

    @if($errors->any())
        <div class="erg-alert erg-alert-red mb-4" role="alert">
            <i class="fas fa-exclamation-circle mt-0.5"></i>
            <ul>@foreach($errors->all() as $fehler)<li>{{ $fehler }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('ergebnisboards.store') }}" class="flex flex-col gap-5">
        @csrf

        <section class="erg-card">
            <div class="erg-card-body flex flex-col gap-3">
                <h2 class="erg-card-title"><i class="fas fa-shapes"></i> Vorlage</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="radiogroup" aria-label="Vorlage">
                    @foreach($vorlagen as $schluessel => $v)
                        <label class="erg-vorlage">
                            <input type="radio" name="vorlage" value="{{ $schluessel }}" class="sr-only" @checked($vorlageGewaehlt === $schluessel)>
                            @include('ergebnisboards.vorlagen.partials.mini', ['v' => $v])
                            <div class="flex items-center gap-2">
                                <i class="{{ $v['icon'] }} text-emerald-700"></i>
                                <span class="text-sm font-bold text-slate-900">{{ $v['titel'] }}</span>
                                @if(!empty($v['eigene']))<span class="erg-chip text-[10px]">eigene</span>@endif
                            </div>
                            @if($v['beschreibung'])
                                <p class="text-xs text-slate-500 leading-snug">{{ $v['beschreibung'] }}</p>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="erg-card">
            <div class="erg-card-body flex flex-col gap-4">
                <h2 class="erg-card-title"><i class="fas fa-heading"></i> Kopf</h2>
                <div>
                    <label class="erg-label" for="titel">Titel <span class="erg-required">*</span></label>
                    <input id="titel" name="titel" type="text" class="erg-input" required maxlength="255" value="{{ old('titel') }}"
                           placeholder="z. B. Ergebnisse des Pädagogischen Tages">
                </div>
                <div>
                    <label class="erg-label" for="untertitel">Untertitel</label>
                    <input id="untertitel" name="untertitel" type="text" class="erg-input" maxlength="500" value="{{ old('untertitel') }}">
                </div>
                <div>
                    <label class="erg-label" for="kopfzeile">Stand / Gremium</label>
                    <input id="kopfzeile" name="kopfzeile" type="text" class="erg-input" maxlength="255" value="{{ old('kopfzeile') }}"
                           placeholder="z. B. Gesamtkollegium • Schuljahr 2026/27">
                </div>
            </div>
        </section>

        <section class="erg-card">
            <div class="erg-card-body flex flex-col gap-4">
                <h2 class="erg-card-title"><i class="fas fa-link"></i> Verknüpfung <span class="text-xs font-normal text-slate-400">optional</span></h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="erg-label" for="meeting_id">Sitzung</label>
                        <select id="meeting_id" name="meeting_id" class="erg-select">
                            <option value="">– keine –</option>
                            @foreach($meetingListe as $m)
                                <option value="{{ $m['id'] }}" @selected($meetingGewaehlt === (string) $m['id'])>{{ $m['titel'] }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="meeting_lesen" value="0">
                        <label class="erg-check mt-2">
                            <input type="checkbox" name="meeting_lesen" value="1" @checked(old('meeting_lesen'))>
                            <span>Alle Sitzungsteilnehmenden dürfen lesen</span>
                        </label>
                    </div>
                    <div>
                        <label class="erg-label" for="theme_id">Thema</label>
                        <select id="theme_id" name="theme_id" class="erg-select">
                            <option value="">– keines –</option>
                            @foreach($themenListe as $t)
                                <option value="{{ $t['id'] }}" @selected($themeGewaehlt === (string) $t['id'])>{{ $t['titel'] }}</option>
                            @endforeach
                        </select>
                        <p class="erg-hint">Nötig für „Karte → Aufgabe“: Aufgaben entstehen in diesem Thema.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="erg-card">
            <div class="erg-card-body flex flex-col gap-3">
                <h2 class="erg-card-title"><i class="fas fa-user-shield"></i> Zugriff</h2>
                @include('ergebnisboards.partials.zugriff_picker', ['optionen' => $optionen, 'auswahl' => $alteZugriffe, 'formular' => true])
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('ergebnisboards.index') }}" class="erg-btn erg-btn-ghost">Abbrechen</a>
            <button type="submit" class="erg-btn erg-btn-primary"><i class="fas fa-check"></i> Board anlegen</button>
        </div>
    </form>
</div>
@endsection
