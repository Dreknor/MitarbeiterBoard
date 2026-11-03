@extends('layouts.app')

@push('css')
    @vite('resources/css/calendar.css')
@endpush

@push('js')
    @vite('resources/js/calendar.js')
@endpush

@section('content')
@php
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP;
    $kalenderFrontend = $kalender->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'farbe' => $c->farbe])->values();
    $alt = [
        'kalender_ids' => array_map('intval', (array) old('kalender_ids', [])),
        'auswahl'      => old('auswahl') !== null ? array_map('intval', (array) old('auswahl')) : null,
        'hinweise'     => (array) old('hinweise', []),
        'hinweis_alle' => old('hinweis_alle', ''),
    ];
@endphp
<div class="calendar-wrapper px-4 py-4 max-md:px-2 max-w-5xl mx-auto"
     x-data="icsImport"
     data-eintraege='{!! json_encode($eintraege, $jsonFlags) !!}'
     data-kalender='{!! json_encode($kalenderFrontend, $jsonFlags) !!}'
     data-alt='{!! json_encode($alt, $jsonFlags) !!}'
     data-max="{{ $maxAuswahl }}">

    {{-- Kopf --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div class="min-w-0">
            <a href="{{ route('calendar.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800">
                <i class="fas fa-arrow-left text-xs"></i> Zurück zum Kalender
            </a>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 leading-tight">Termine importieren</h1>
            <p class="mt-0.5 text-sm text-gray-500 truncate">
                <i class="far fa-file mr-1"></i>{{ $dateiname }} · {{ count($eintraege) }} Termin(e) gefunden
            </p>
        </div>
    </div>

    @if($kalender->isEmpty())
        <div class="p-4 bg-amber-50 border border-amber-300 rounded-lg text-sm text-amber-800">
            Du hast in keinen Kalender Schreibrecht – ein Import ist daher nicht möglich.
        </div>
    @else
    <form method="POST" action="{{ route('calendar.import.store', $token) }}" @submit="vorAbsenden($event)">
        @csrf

        {{-- Auswahl und Hinweise unabhängig vom aktuellen Filter übertragen --}}
        <template x-for="i in gewaehlt" :key="'a' + i">
            <input type="hidden" name="auswahl[]" :value="i">
        </template>
        <template x-for="[i, h] in Object.entries(hinweise).filter(([, h]) => (h || '').trim() !== '')" :key="'h' + i">
            <input type="hidden" :name="'hinweise[' + i + ']'" :value="h">
        </template>

        {{-- Ziel + Hinweis für alle --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <section class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900 mb-0.5">1. Zielkalender</h2>
                <p class="text-xs text-gray-500 mb-2">Die ausgewählten Termine werden in jeden gewählten Kalender in OX eingetragen.</p>
                <div class="flex flex-col gap-1">
                    <template x-for="k in kalender" :key="k.id">
                        <label class="flex items-center gap-2 px-2.5 py-1.5 border rounded-md cursor-pointer transition-colors"
                               :class="ziel.includes(k.id) ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'">
                            <input type="checkbox" name="kalender_ids[]" :value="k.id" x-model.number="ziel" class="w-4 h-4 accent-blue-600">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background-color: ' + k.farbe"></span>
                            <span class="text-sm text-gray-800 truncate" x-text="k.name"></span>
                        </label>
                    </template>
                </div>
            </section>

            <section class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900 mb-0.5">2. Hinweis für alle Termine <span class="font-normal text-gray-500">(optional)</span></h2>
                <p class="text-xs text-gray-500 mb-2">Wird an die Beschreibung jedes importierten Termins angehängt. Einzelne Hinweise ergänzt du unten pro Termin.</p>
                <textarea name="hinweis_alle" x-model="hinweisAlle" rows="4" maxlength="1000" class="cal-input"
                          placeholder="z. B. „Aus dem Jahresplan der Stadt übernommen – bitte Aushang beachten.“"></textarea>
            </section>
        </div>

        {{-- Termine --}}
        <section class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-900">3. Termine auswählen</h2>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <input type="search" x-model="suche" placeholder="Filtern…"
                           class="border border-gray-300 rounded-md px-2 py-1 text-xs w-40 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <label class="inline-flex items-center gap-1.5 text-gray-600 cursor-pointer select-none">
                        <input type="checkbox" x-model="vergangeneAusblenden" class="accent-blue-600"> Vergangene ausblenden
                    </label>
                    <span class="text-gray-300">|</span>
                    <button type="button" @click="alleSichtbarenWaehlen(true)" class="text-blue-600 hover:text-blue-800 hover:underline">alle sichtbaren</button>
                    <button type="button" @click="alleSichtbarenWaehlen(false)" class="text-gray-600 hover:text-gray-800 hover:underline">keine</button>
                    <button type="button" @click="nurNeueWaehlen()" class="text-gray-600 hover:text-gray-800 hover:underline"
                            title="Zukünftige Termine, die in den Zielkalendern noch nicht existieren">nur neue</button>
                </div>
            </div>

            <ul class="divide-y divide-gray-100">
                <template x-for="e in sichtbar" :key="e.index">
                    <li class="px-4 py-3 transition-colors" :class="gewaehlt.includes(e.index) ? 'bg-white' : 'bg-gray-50/70'">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" :value="e.index" x-model.number="gewaehlt"
                                   :id="'imp-' + e.index"
                                   class="mt-1 w-4 h-4 shrink-0 accent-blue-600 cursor-pointer">

                            <label :for="'imp-' + e.index" class="w-36 max-sm:w-28 shrink-0 cursor-pointer">
                                <span class="block text-sm font-medium text-gray-900" x-text="datum(e)"></span>
                                <span class="block text-xs text-gray-500" x-text="zeit(e)"></span>
                            </label>

                            <div class="flex-1 min-w-0">
                                <label :for="'imp-' + e.index" class="block cursor-pointer">
                                    <span class="text-sm font-medium break-words" :class="gewaehlt.includes(e.index) ? 'text-gray-900' : 'text-gray-500'" x-text="e.titel"></span>
                                </label>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                    <span x-show="e.ort"><i class="fas fa-map-marker-alt mr-1"></i><span x-text="e.ort"></span></span>
                                    <span x-show="e.rrule" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded">
                                        <i class="fas fa-redo text-[10px]"></i><span x-text="rruleHuman(e.rrule)"></span>
                                    </span>
                                    <span x-show="e.vergangen" class="px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded">vergangen</span>
                                    <template x-if="duplikateImZiel(e).length">
                                        <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded"
                                              x-text="'bereits vorhanden in ' + duplikateImZiel(e).map(d => d.name).join(', ')"></span>
                                    </template>
                                    <template x-for="w in e.warnungen" :key="w">
                                        <span class="px-1.5 py-0.5 bg-orange-50 text-orange-700 rounded"><i class="fas fa-exclamation-triangle mr-1"></i><span x-text="w"></span></span>
                                    </template>
                                </div>

                                <p x-show="e.beschreibung && offeneBeschreibung.includes(e.index)" x-cloak
                                   class="mt-1.5 text-xs text-gray-600 whitespace-pre-line break-words" x-text="e.beschreibung"></p>

                                {{-- Hinweis pro Termin --}}
                                <div x-show="offeneHinweise.includes(e.index) || (hinweise[e.index] || '').length" x-cloak class="mt-2">
                                    <label :for="'hinweis-' + e.index" class="sr-only">Hinweis</label>
                                    <input type="text" :id="'hinweis-' + e.index"
                                           x-model="hinweise[e.index]" maxlength="1000"
                                           class="cal-input" placeholder="Hinweis zu diesem Termin (wird an die Beschreibung angehängt)">
                                </div>

                                <div class="mt-1.5 flex gap-3 text-xs">
                                    <button type="button" @click="hinweisOeffnen(e.index)"
                                            class="text-blue-600 hover:text-blue-800 hover:underline">
                                        <i class="fas fa-comment-medical mr-0.5"></i><span x-text="(hinweise[e.index] || '').length ? 'Hinweis bearbeiten' : 'Hinweis ergänzen'"></span>
                                    </button>
                                    <button type="button" x-show="e.beschreibung" @click="umschalten(offeneBeschreibung, e.index)"
                                            class="text-gray-500 hover:text-gray-800 hover:underline"
                                            x-text="offeneBeschreibung.includes(e.index) ? 'Beschreibung ausblenden' : 'Beschreibung anzeigen'"></button>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
                <li x-show="sichtbar.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                    Keine Termine für den aktuellen Filter.
                </li>
            </ul>
        </section>

        {{-- Aktionsleiste --}}
        <div class="sticky bottom-0 z-10 mt-4 -mx-4 max-md:-mx-2 px-4 py-3 bg-white/95 backdrop-blur border-t border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-700">
                <strong x-text="gewaehlt.length"></strong> von <span x-text="eintraege.length"></span> Terminen ausgewählt
                <span x-show="ziel.length > 1" class="text-gray-500">· je <span x-text="ziel.length"></span> Kalender</span>
                <p x-show="gewaehlt.length > max" x-cloak class="text-xs text-red-600">Pro Import höchstens <span x-text="max"></span> Termine.</p>
                <p x-show="ziel.length === 0" class="text-xs text-red-600">Bitte mindestens einen Zielkalender wählen.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('calendar.index') }}"
                   class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-900 rounded-md">Abbrechen</a>
                <button type="submit"
                        :disabled="sendet || gewaehlt.length === 0 || ziel.length === 0 || gewaehlt.length > max"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md">
                    <span x-show="!sendet"><i class="fas fa-file-import mr-1"></i>Importieren</span>
                    <span x-show="sendet" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i>Wird nach OX übertragen…</span>
                </button>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection
