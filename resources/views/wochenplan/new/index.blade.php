@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@php
    $zeitraum = $filter['zeitraum'] ?? 'alle';
    $typ = $filter['typ'] ?? 'alle';
    $link = fn (array $aenderung) => route('wp.index', array_filter(array_merge($filter, $aenderung), fn ($v) => $v !== null && $v !== ''));
@endphp

@section('content')
<div class="wp-wrapper">
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Wochenpläne</h1>
            <p class="wp-subtitle">
                @if($darfAlles)
                    Pläne aller Klassen
                @else
                    Pläne Ihrer Klassen{{ $klassen->isNotEmpty() ? ': '.$klassen->pluck('name')->join(', ') : '' }}
                @endif
            </p>
        </div>
        <div class="wp-btn-group" data-tour="wp-navigation">
            <a href="{{ route('wp.hilfe') }}" class="wp-btn wp-btn-ghost wp-btn-sm">❓ Hilfe</a>
            @if($darfSchreiben)
                <a href="{{ route('wp.bibliothek.index') }}" class="wp-btn wp-btn-secondary wp-btn-sm">📚 Bibliothek</a>
                <a href="{{ route('wp.vorlagen.index') }}" class="wp-btn wp-btn-secondary wp-btn-sm">📄 Vorlagen</a>
            @endif
            @can('manage wochenplan-faecher')
                <a href="{{ route('wp.faecher.index') }}" class="wp-btn wp-btn-secondary wp-btn-sm">Fächer</a>
            @endcan
            @can('manage wochenplan-formatvorlagen')
                <a href="{{ route('wp.formatvorlagen.index') }}" class="wp-btn wp-btn-secondary wp-btn-sm">Formatvorlagen</a>
            @endcan
            @can('edit settings')
                <a href="{{ route('wp.einstellungen') }}" class="wp-btn wp-btn-secondary wp-btn-sm">⚙ Einstellungen</a>
            @endcan
            @if($darfSchreiben)
                <a href="{{ route('wp.import') }}" class="wp-btn wp-btn-secondary" title="Wochenpläne aus Word-Dateien (.docx) übernehmen">⬆ Word-Import</a>
                <a href="{{ route('wp.create') }}" class="wp-btn wp-btn-primary">+ Neuer Plan</a>
            @endif
        </div>
    </div>

    @if(! $darfAlles && $klassen->isEmpty())
        <div class="wp-note wp-note-warning mb-4">
            Ihnen ist noch keine Klasse zugeordnet. Wochenpläne sehen Sie für die Klassen, denen Sie im Pädagogischen Tagebuch zugeordnet sind.
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" action="{{ route('wp.index') }}" class="wp-card mb-4" data-tour="wp-filter">
        <div class="wp-card-body space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label class="wp-label" for="wp-q">Suche</label>
                    <input id="wp-q" type="search" name="q" value="{{ $filter['q'] ?? '' }}" class="wp-input" placeholder="Plan, Klasse oder Kind">
                </div>
                <div>
                    <label class="wp-label" for="wp-klasse">Klasse</label>
                    <select id="wp-klasse" name="klasse_id" class="wp-select" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        @foreach($klassen as $klasse)
                            <option value="{{ $klasse->id }}" @selected(($filter['klasse_id'] ?? '') == $klasse->id)>{{ $klasse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="wp-label" for="wp-schuljahr">Schuljahr</label>
                    <select id="wp-schuljahr" name="schuljahr" class="wp-select" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        @foreach($schuljahre as $jahr)
                            <option value="{{ $jahr }}" @selected(($filter['schuljahr'] ?? '') == $jahr)>{{ $jahr }}/{{ substr($jahr + 1, -2) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="wp-label" for="wp-woche">Woche</label>
                    <input id="wp-woche" type="date" name="woche" value="{{ $filter['woche'] ?? '' }}" class="wp-input" onchange="this.form.submit()">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="wp-pills" aria-label="Zeitraum">
                    @foreach(['alle' => 'Alle', 'aktuell' => 'Diese Woche', 'kommend' => 'Geplant', 'vergangen' => 'Archiv'] as $wert => $text)
                        <a href="{{ $link(['zeitraum' => $wert === 'alle' ? null : $wert, 'page' => null]) }}" class="wp-pill {{ $zeitraum === $wert ? 'is-active' : '' }}">{{ $text }}</a>
                    @endforeach
                </div>
                <div class="wp-pills" aria-label="Typ">
                    @foreach(['alle' => 'Klassenpläne + Einzelpläne', 'klassenplan' => 'Nur Klassenpläne', 'schuelerplan' => 'Alle Kinderpläne'] as $wert => $text)
                        <a href="{{ $link(['typ' => $wert === 'alle' ? null : $wert, 'page' => null]) }}" class="wp-pill {{ $typ === $wert ? 'is-active' : '' }}">{{ $text }}</a>
                    @endforeach
                </div>
                @if($darfAlles)
                    <label class="wp-check ml-auto">
                        <input type="checkbox" name="meine" value="1" @checked(! empty($filter['meine'])) onchange="this.form.submit()"> Nur meine Klassen
                    </label>
                @endif
                <input type="hidden" name="zeitraum" value="{{ $filter['zeitraum'] ?? '' }}">
                <input type="hidden" name="typ" value="{{ $filter['typ'] ?? '' }}">
                <button type="submit" class="wp-btn wp-btn-secondary wp-btn-sm {{ $darfAlles ? '' : 'ml-auto' }}">Filtern</button>
                @if(collect($filter)->filter()->isNotEmpty())
                    <a href="{{ route('wp.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Zurücksetzen</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Pläne --}}
    @if($plaene->isEmpty())
        <div class="wp-card wp-empty">
            <div class="wp-empty-icon">🗓</div>
            <p class="font-semibold text-gray-700">Keine Pläne gefunden.</p>
            @if($darfSchreiben)
                <p class="mt-1">Legen Sie einen <a href="{{ route('wp.create') }}" class="font-semibold text-blue-600">neuen Plan</a> an oder starten Sie mit einer Vorlage.</p>
            @endif
        </div>
    @else
        <div class="wp-grid" data-tour="wp-liste">
            @foreach($plaene as $plan)
                @include('wochenplan.new.components.plan-card', ['plan' => $plan])
            @endforeach
        </div>
        <div class="mt-6">@include('wochenplan.new.partials.seiten', ['paginator' => $plaene])</div>
    @endif

    {{-- Vorlagen --}}
    @if($darfSchreiben && $vorlagen->isNotEmpty())
        <section class="mt-8">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="wp-section-title">Mit einer Vorlage starten</h2>
                <a href="{{ route('wp.vorlagen.index') }}" class="text-sm font-semibold text-blue-600">Alle Vorlagen →</a>
            </div>
            <div class="wp-pills">
                @foreach($vorlagen->take(12) as $vorlage)
                    <a href="{{ route('wp.create', ['vorlage' => $vorlage->id]) }}" class="wp-pill">📄 {{ $vorlage->vorlage_name ?? $vorlage->name }} <span class="text-gray-400">({{ $vorlage->aufgaben_count }})</span></a>
                @endforeach
            </div>
        </section>
    @endif
</div>

<x-tour id="wochenplan" :version="1" :steps="[
    ['target' => 'wp-navigation', 'title' => 'Überarbeiteter Wochenplan', 'text' => 'Sie sehen die Pläne Ihrer Klassen. Neu sind die Aufgaben-Bibliothek, Einstellungen für die Nextcloud-Ablage und der Fortschritt je Kind.'],
    ['target' => 'wp-filter', 'title' => 'Suchen und filtern', 'text' => 'Nach Plan, Klasse oder Kind suchen, nach Schuljahr oder Woche filtern. „Archiv“ zeigt vergangene Pläne.'],
    ['target' => 'wp-liste', 'title' => 'Pläne', 'text' => 'Kinderpläne erscheinen unter ihrem Klassenplan. Pläne dieser Woche sind hervorgehoben, ☁ zeigt die Ablage in der Nextcloud.'],
]" />
@endsection
