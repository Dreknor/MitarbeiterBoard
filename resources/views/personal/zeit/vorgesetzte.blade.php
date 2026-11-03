@extends('layouts.app')

@section('title')
    Vorgesetzte & Stellvertretungen
@endsection

@section('site-title')
    Mitarbeiterverwaltung
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@section('content')
<div class="zeit-wrapper"
     x-data="{ auswahl: [], alleIds: @js($personen->pluck('id')->values()),
               get alleGewaehlt() { return this.alleIds.length > 0 && this.auswahl.length === this.alleIds.length; },
               umschalten() { this.auswahl = this.alleGewaehlt ? [] : [...this.alleIds]; } }">

    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="zw-page-title">Vorgesetzte &amp; Stellvertretungen</h1>
            <p class="zw-page-sub">Legt fest, wer Urlaub genehmigt und Arbeitszeitnachweise prüft. Stellvertretungen dürfen alles, was die vertretene Leitung darf.</p>
        </div>
        @if($ohneVorgesetzte > 0)
            <a href="{{ route('personal.vorgesetzte.index', ['ohne' => 1]) }}" class="zw-btn zw-btn-warning">
                <i class="fas fa-user-slash"></i> {{ $ohneVorgesetzte }} ohne Vorgesetzte
            </a>
        @endif
    </div>

    <nav class="zw-tabs mb-5" x-data>
        <a href="#zuordnung" class="zw-tab is-active"><i class="fas fa-sitemap"></i> Zuordnung</a>
        <a href="#stellvertretungen" class="zw-tab"><i class="fas fa-user-friends"></i> Leitungen &amp; Stellvertretungen</a>
    </nav>

    {{-- ================= Zuordnung ================= --}}
    <section id="zuordnung" class="zw-card mb-6">
        <form method="get" class="zw-card-head flex-col sm:flex-row items-stretch sm:items-end">
            <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3 w-full">
                <div>
                    <label class="zw-label" for="group_id">Gruppe</label>
                    <select name="group_id" id="group_id" class="zw-select" onchange="this.form.submit()">
                        <option value="">Alle Gruppen</option>
                        @foreach($gruppen as $gruppe)
                            <option value="{{ $gruppe->id }}" @selected((string) $filter['group_id'] === (string) $gruppe->id)>{{ $gruppe->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="zw-label" for="suche">Name</label>
                    <input type="search" name="suche" id="suche" value="{{ $filter['suche'] }}" class="zw-input" placeholder="Suchen …">
                </div>
                <label class="zw-check self-end">
                    <input type="checkbox" name="ohne" value="1" @checked($filter['ohne']) onchange="this.form.submit()">
                    nur ohne Vorgesetzte
                </label>
            </div>
            <button type="submit" class="zw-btn zw-btn-secondary"><i class="fas fa-filter"></i> Filtern</button>
        </form>

        <form action="{{ route('personal.vorgesetzte.update') }}" method="post">
            @csrf

            {{-- Aktionsleiste --}}
            <div class="sticky top-0 z-20 flex flex-col lg:flex-row lg:items-center gap-3 px-4 py-3 sm:px-5 bg-blue-50 border-b border-blue-100">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 shrink-0">
                    <input type="checkbox" class="w-4 h-4" :checked="alleGewaehlt" @change="umschalten()">
                    <span x-text="auswahl.length + ' von ' + alleIds.length + ' ausgewählt'"></span>
                </label>
                <div class="flex flex-col sm:flex-row gap-2 flex-1 lg:justify-end">
                    <select name="superior_id" class="zw-select sm:max-w-xs" aria-label="Vorgesetzte Person">
                        <option value="">– vorgesetzte Person wählen –</option>
                        <optgroup label="Leitungen">
                            @foreach($leitungen as $leitung)
                                <option value="{{ $leitung->id }}">{{ $leitung->name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Alle Personen">
                            @foreach($alle as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <button type="submit" class="zw-btn zw-btn-primary" :disabled="auswahl.length === 0">
                        <i class="fas fa-check"></i> Zuordnen
                    </button>
                    <button type="submit" name="entfernen" value="1" class="zw-btn zw-btn-secondary" :disabled="auswahl.length === 0"
                            onclick="return confirm('Vorgesetzte bei den ausgewählten Personen entfernen?')">
                        <i class="fas fa-unlink"></i> Entfernen
                    </button>
                </div>
            </div>

            @if($personen->isEmpty())
                <div class="zw-empty"><i class="fas fa-users"></i> Keine Personen für diese Auswahl.</div>
            @else
                <ul class="zw-list">
                    @foreach($personen as $person)
                        <li>
                            <label class="zw-row cursor-pointer hover:bg-gray-50" :class="auswahl.includes({{ $person->id }}) && 'bg-blue-50/60'">
                                <input type="checkbox" name="user_ids[]" value="{{ $person->id }}" x-model.number="auswahl" class="w-4 h-4 shrink-0">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900 truncate">{{ $person->name }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ $person->groups_rel->pluck('name')->take(4)->implode(', ') }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    @if($person->superior)
                                        <span class="text-sm text-gray-700"><i class="fas fa-level-up-alt text-gray-400 mr-1"></i>{{ $person->superior->name }}</span>
                                    @else
                                        <span class="zw-badge zw-badge-amber">ohne Vorgesetzte</span>
                                    @endif
                                </div>
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif
        </form>
    </section>

    {{-- ================= Stellvertretungen ================= --}}
    <section id="stellvertretungen" class="zw-card">
        <div class="zw-card-head">
            <h2 class="zw-card-title"><i class="fas fa-user-friends"></i> Leitungen &amp; Stellvertretungen</h2>
            <span class="text-xs text-gray-500">z. B. zweite Leitung eines Bereichs als Stellvertretung eintragen</span>
        </div>
        @if($leitungen->isEmpty())
            <div class="zw-empty"><i class="fas fa-user-tie"></i> Noch keine Leitungen – zuerst Vorgesetzte zuordnen oder das Recht „approve holidays“ vergeben.</div>
        @else
            <ul class="zw-list">
                @foreach($leitungen as $leitung)
                    <li class="px-4 py-3 sm:px-5 flex flex-col lg:flex-row lg:items-center gap-3">
                        <div class="lg:w-64 shrink-0">
                            <div class="font-semibold text-gray-900">{{ $leitung->name }}</div>
                            <div class="text-xs text-gray-500">{{ $leitung->subordinates_count }} direkt unterstellt</div>
                        </div>
                        <div class="flex-1 flex flex-wrap items-center gap-1.5">
                            @forelse($leitung->deputies as $vertretung)
                                <span class="zw-badge zw-badge-blue py-1">
                                    {{ $vertretung->name }}
                                    <form action="{{ route('personal.vorgesetzte.deputies.destroy', [$leitung->id, $vertretung->id]) }}" method="post" class="inline" data-confirm="{{ $vertretung->name }} als Stellvertretung von {{ $leitung->name }} entfernen?">
                                        @csrf @method('delete')
                                        <button type="submit" class="ml-1 hover:text-red-600" title="Entfernen"><i class="fas fa-times"></i></button>
                                    </form>
                                </span>
                            @empty
                                <span class="text-sm text-gray-400">keine Stellvertretung</span>
                            @endforelse
                        </div>
                        <form action="{{ route('personal.vorgesetzte.deputies.store', $leitung->id) }}" method="post" class="flex gap-2 lg:w-96">
                            @csrf
                            <select name="deputy_id" class="zw-select" required aria-label="Stellvertretung hinzufügen">
                                <option value="">+ Stellvertretung …</option>
                                @foreach($leitungen->where('id', '!=', $leitung->id) as $kandidat)
                                    <option value="{{ $kandidat->id }}">{{ $kandidat->name }}</option>
                                @endforeach
                                <optgroup label="Alle Personen">
                                    @foreach($alle->where('id', '!=', $leitung->id) as $kandidat)
                                        <option value="{{ $kandidat->id }}">{{ $kandidat->name }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            <button type="submit" class="zw-btn zw-btn-secondary shrink-0"><i class="fas fa-plus"></i></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
