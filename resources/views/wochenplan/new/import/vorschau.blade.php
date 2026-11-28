@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@php
    $klassenNamen = $klassen->pluck('name', 'id');
    $anzahl = collect($vorschau)->sum(fn ($d) => count($d['plaene']));
@endphp

@section('content')
<div class="wp-wrapper">
    <a href="{{ route('wp.import') }}" class="wp-back">← Andere Dateien wählen</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Import prüfen</h1>
            <p class="wp-subtitle">{{ $anzahl }} Plan/Pläne in {{ count($vorschau) }} Datei(en) erkannt. Ausgewählte Pläne werden angelegt, bereits vorhandene (gleicher Name und Beginn) übersprungen.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="wp-note wp-note-warning mb-4">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('wp.import.ausfuehren', $token) }}" class="space-y-4">
        @csrf

        @foreach($vorschau as $datei)
            <section class="wp-card">
                <div class="wp-card-head">
                    <h2 class="wp-card-title">📄 {{ $datei['name'] }}</h2>
                    <span class="text-xs text-gray-500">{{ count($datei['plaene']) }} Plan/Pläne</span>
                </div>
                <div class="wp-card-body space-y-3">
                    @if($datei['fehler'])
                        <div class="wp-note wp-note-warning">{{ $datei['fehler'] }}</div>
                    @endif
                    @foreach($datei['hinweise'] as $hinweis)
                        <div class="wp-note wp-note-warning">{{ $hinweis }}</div>
                    @endforeach

                    @foreach($datei['plaene'] as $p)
                        @php
                            $feld = 'plaene['.$p['schluessel'].']';
                            $bereit = ! $p['vorhanden'] && $p['von'] && $p['bis'] && ($p['kind'] ? $p['schueler_id'] : $p['klassen']);
                        @endphp
                        <div class="rounded-xl border border-gray-200 p-3">
                            <div class="flex flex-wrap items-start gap-3">
                                <label class="inline-flex items-center gap-2 font-semibold text-gray-800">
                                    <input type="hidden" name="{{ $feld }}[importieren]" value="0">
                                    <input type="checkbox" name="{{ $feld }}[importieren]" value="1" @checked($bereit)>
                                    {{ $p['name'] }}
                                    @if($p['kind'])<span class="wp-badge wp-badge-violet">Kinderplan: {{ $p['kind'] }}</span>@else<span class="wp-badge wp-badge-blue">Lerngruppe{{ $p['gruppe'] ? ' '.$p['gruppe'] : '' }}</span>@endif
                                    @if($p['vorhanden'])<span class="wp-badge wp-badge-amber">schon vorhanden</span>@endif
                                </label>
                                <span class="text-xs text-gray-500">{{ $p['anzahl']['aufgaben'] }} Aufgaben · {{ $p['anzahl']['freiarbeit'] }} Freiarbeit · {{ $p['anzahl']['uebungen'] }} tägl. Übungen · {{ $p['anzahl']['faecher'] }} Fächer</span>
                            </div>

                            @foreach($p['hinweise'] as $hinweis)
                                <p class="mt-1 text-xs text-amber-700">⚠ {{ $hinweis }}</p>
                            @endforeach

                            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                                <label>
                                    <span class="wp-label">von</span>
                                    <input type="date" class="wp-input wp-input-sm" name="{{ $feld }}[von]" value="{{ $p['von'] }}">
                                </label>
                                <label>
                                    <span class="wp-label">bis</span>
                                    <input type="date" class="wp-input wp-input-sm" name="{{ $feld }}[bis]" value="{{ $p['bis'] }}">
                                </label>
                                @if($p['kind'])
                                    <label>
                                        <span class="wp-label">Kind</span>
                                        <select class="wp-select wp-input-sm" name="{{ $feld }}[schueler_id]">
                                            <option value="">– nicht importieren –</option>
                                            @foreach($kinder as $klasseId => $liste)
                                                <optgroup label="{{ $klassenNamen[$klasseId] ?? '' }}">
                                                    @foreach($liste as $kind)
                                                        <option value="{{ $kind->id }}" @selected((int) $p['schueler_id'] === $kind->id)>{{ $kind->vorname }} {{ $kind->nachname }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </label>
                                @else
                                    <div>
                                        <span class="wp-label">Klassen (je Klasse ein Klassenplan)</span>
                                        <div class="flex flex-wrap gap-x-3 gap-y-1">
                                            @foreach($klassen as $klasse)
                                                @if(in_array($klasse->id, $p['klassen'], true) || ($p['stufe'] && str_ends_with($klasse->name, ' '.$p['stufe'])))
                                                    <label class="inline-flex items-center gap-1 text-sm"><input type="checkbox" name="{{ $feld }}[klassen][]" value="{{ $klasse->id }}" @checked(in_array($klasse->id, $p['klassen'], true))> {{ $klasse->name }}</label>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-blue-600">Inhalt anzeigen</summary>
                                <div class="mt-2 space-y-2 text-sm text-gray-700">
                                    @if($p['inhalt']['uebungen'])
                                        <div><strong>Tägliche Übung:</strong>
                                            <ul class="ml-4 list-disc">@foreach($p['inhalt']['uebungen'] as $u)<li class="whitespace-pre-line"><span class="wp-badge wp-badge-blue">{{ $u['kuerzel'] ?? '–' }}</span> {{ $u['text'] }}</li>@endforeach</ul>
                                        </div>
                                    @endif
                                    @foreach($p['inhalt']['faecher'] as $f)
                                        <div><strong>{{ str_replace("\n", ' / ', str_replace('✍', '', $f['label'])) }}</strong>
                                            <ol class="ml-4 list-decimal">@foreach($f['aufgaben'] as $a)<li class="whitespace-pre-line">@if($a['freiwillig'])<span class="font-bold text-amber-600">*</span> @endif{{ $a['text'] }}</li>@endforeach</ol>
                                        </div>
                                    @endforeach
                                    @if($p['inhalt']['freiarbeit'])
                                        <div><strong>Freiarbeit:</strong> {{ collect($p['inhalt']['freiarbeit'])->map(fn ($fa) => ($fa['fach'] ? $fa['fach'].': ' : '').$fa['text'])->implode(' · ') }}</div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end gap-2">
            <a href="{{ route('wp.import') }}" class="wp-btn wp-btn-secondary">Abbrechen</a>
            <button type="submit" class="wp-btn wp-btn-primary">Ausgewählte Pläne importieren</button>
        </div>
    </form>
</div>
@endsection
