@extends('layouts.app')

@section('title')
    Dienstpläne
@endsection

@section('site-title')
    Dienstplanung
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $felder = ['function' => 'Aufgabe', 'start' => 'Arbeitsbeginn', 'end' => 'Arbeitsende', 'event' => 'Terminname'];
    $wochentage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
@endphp

@section('content')
<div class="zeit-wrapper">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="zw-page-title">Dienstpläne</h1>
            <p class="zw-page-sub">Wochenpläne je Abteilung planen, veröffentlichen und Änderungen mitteilen.</p>
        </div>
        <a href="{{ route('roster.mine') }}" class="zw-btn zw-btn-secondary"><i class="fas fa-calendar-week"></i> Mein Dienstplan</a>
    </div>

    @forelse($departments as $department)
        @php
            [$fensterStart, $fensterEnde] = $department->rosterDayWindow();
            $normal = $department->rosters->where('type', 'normal');
            $aktuell = $normal->filter(fn ($r) => $r->start_date->copy()->endOfWeek()->gte($aktuelleWoche))->sortBy('start_date');
            $vergangen = $normal->filter(fn ($r) => $r->start_date->copy()->endOfWeek()->lt($aktuelleWoche));
            $vorlagen = $department->rosters->where('type', 'template');
        @endphp
        <section class="zw-card mb-5" x-data="{ einstellungen: false, alt: false }">
            <div class="zw-card-head">
                <h2 class="zw-card-title"><i class="fas fa-columns"></i> {{ $department->name }}</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="zw-btn zw-btn-sm zw-btn-ghost" @click="einstellungen = !einstellungen" :aria-expanded="einstellungen.toString()">
                        <i class="fas fa-sliders-h"></i><span class="hidden sm:inline">Einstellungen</span>
                    </button>
                    <a href="{{ route('roster.create', $department->id) }}" class="zw-btn zw-btn-sm zw-btn-primary"><i class="fas fa-plus"></i> Neuer Plan</a>
                </div>
            </div>

            {{-- Einstellungen: Tagesfenster + Checks --}}
            <div x-show="einstellungen" x-cloak class="border-b border-gray-100 bg-gray-50">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 zw-card-body">
                    <form action="{{ route('roster.department.settings', $department->id) }}" method="post" class="flex flex-col gap-3">
                        @csrf @method('put')
                        <p class="zw-section-title">Tagesfenster im Raster</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="zw-label">von</label><input type="time" name="roster_day_start" value="{{ $fensterStart }}" class="zw-input" required></div>
                            <div><label class="zw-label">bis</label><input type="time" name="roster_day_end" value="{{ $fensterEnde }}" class="zw-input" required></div>
                        </div>
                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-secondary self-start">Speichern</button>
                    </form>

                    <div class="lg:col-span-2 flex flex-col gap-3">
                        <p class="zw-section-title">Checks (Mindestbesetzung)</p>
                        @if($department->roster_checks->isNotEmpty())
                            <ul class="flex flex-col gap-1">
                                @foreach($department->roster_checks->sortBy('weekday')->groupBy('check_name') as $name => $checks)
                                    @php $check = $checks->first(); @endphp
                                    <li class="flex items-center gap-2 rounded-lg bg-white border border-gray-200 px-3 py-2 text-sm">
                                        <div class="flex-1 min-w-0">
                                            <span class="font-medium">{{ $name }}</span>
                                            <span class="text-gray-500">– {{ $felder[$check->field_name] ?? $check->field_name }} {{ $check->operator }} {{ $check->value }}, mind. {{ $check->needs }} · {{ $checks->map(fn ($c) => $wochentage[$c->weekday] ?? '?')->implode(', ') }}</span>
                                        </div>
                                        @foreach($checks as $c)
                                            <form action="{{ route('roster.checks.destroy', $c) }}" method="post" data-confirm="Check „{{ $name }}“ ({{ $wochentage[$c->weekday] ?? '' }}) löschen?">
                                                @csrf @method('delete')
                                                <button type="submit" class="text-xs text-red-600 hover:underline" title="für {{ $wochentage[$c->weekday] ?? '' }} löschen">{{ $wochentage[$c->weekday] ?? '?' }} ×</button>
                                            </form>
                                        @endforeach
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <form action="{{ route('roster.checks.store') }}" method="post" class="grid grid-cols-2 md:grid-cols-6 gap-2 items-end">
                            @csrf
                            <input type="hidden" name="department_id" value="{{ $department->id }}">
                            <div class="col-span-2"><label class="zw-label">Bezeichnung</label><input name="check_name" class="zw-input" required placeholder="z. B. Frühdienst ab 6:30"></div>
                            <div class="col-span-2 md:col-span-1"><label class="zw-label">Feld</label>
                                <select name="field_name" class="zw-select">@foreach($felder as $wert => $text)<option value="{{ $wert }}">{{ $text }}</option>@endforeach</select>
                            </div>
                            <div><label class="zw-label">Vergleich</label>
                                <select name="operator" class="zw-select">@foreach(['<', '<=', '=', '>=', '>'] as $op)<option value="{{ $op }}" @selected($op === '=')>{{ $op }}</option>@endforeach</select>
                            </div>
                            <div><label class="zw-label">Wert</label><input name="value" class="zw-input" required placeholder="06:30"></div>
                            <div><label class="zw-label">Anzahl</label><input type="number" min="1" value="1" name="needs" class="zw-input" required></div>
                            <div class="col-span-2 md:col-span-6 flex flex-wrap items-center gap-1.5">
                                @foreach($wochentage as $i => $kurz)
                                    <label class="zw-check py-1"><input type="checkbox" name="weekday[]" value="{{ $i }}" @checked($i < 5)> {{ $kurz }}</label>
                                @endforeach
                                <button type="submit" class="zw-btn zw-btn-sm zw-btn-secondary ml-auto"><i class="fas fa-plus"></i> Check anlegen</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Pläne --}}
            @if($aktuell->isEmpty())
                <div class="zw-empty"><i class="far fa-calendar"></i> Kein aktueller oder kommender Plan. <a href="{{ route('roster.create', $department->id) }}" class="text-blue-600">Jetzt anlegen</a></div>
            @else
                <ul class="zw-list">
                    @foreach($aktuell as $roster)
                        @include('personal.rosters.partials.roster_row', ['roster' => $roster])
                    @endforeach
                </ul>
            @endif

            @if($vorlagen->isNotEmpty())
                <div class="px-4 sm:px-5 pt-4 pb-1 zw-section-title border-t border-gray-100">Vorlagen</div>
                <ul class="zw-list">
                    @foreach($vorlagen->sortByDesc('start_date') as $roster)
                        @include('personal.rosters.partials.roster_row', ['roster' => $roster])
                    @endforeach
                </ul>
            @endif

            @if($vergangen->isNotEmpty())
                <button type="button" class="w-full px-4 py-3 sm:px-5 text-left text-sm font-semibold text-gray-500 border-t border-gray-100 hover:bg-gray-50" @click="alt = !alt">
                    <i class="fas" :class="alt ? 'fa-chevron-down' : 'fa-chevron-right'"></i> Vergangene Pläne ({{ $vergangen->count() }})
                </button>
                <ul class="zw-list" x-show="alt" x-cloak>
                    @foreach($vergangen->take(26) as $roster)
                        @include('personal.rosters.partials.roster_row', ['roster' => $roster])
                    @endforeach
                </ul>
            @endif
        </section>
    @empty
        <div class="zw-card"><div class="zw-empty"><i class="fas fa-columns"></i> Keine Abteilungen mit Dienstplanung in deiner Zuständigkeit.</div></div>
    @endforelse
</div>
@endsection
