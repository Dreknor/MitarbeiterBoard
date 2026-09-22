@extends('layouts.app')
@push('css')
    @vite('resources/css/wochenplan.css')
@endpush
@section('content')
<div class="wp-container p-4 max-w-2xl mx-auto">
    {{-- Zurück-Link --}}
    <a href="{{ route('wp.edit', $wpPlan) }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zum Plan
    </a>
    <h1 class="text-xl font-bold text-gray-900 mb-1">Individuellen Kinderplan erstellen</h1>
    <p class="text-sm text-gray-500 mb-6">Basiert auf: <strong>{{ $wpPlan->name }}</strong></p>

    @if(!$wpPlan->klasse)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
            ⚠️ Dieser Plan ist keiner Klasse zugeordnet. Wähle Schüler/innen aus allen verfügbaren Klassen.
        </div>
    @endif

    <form method="POST" action="{{ route('wp.schuelerplan.store', $wpPlan) }}"
          class="bg-white rounded-lg border border-gray-200 p-5 space-y-5">
        @csrf
        {{-- Schüler-Auswahl --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Schüler/in auswählen <span class="text-red-500">*</span>
                <span class="text-xs text-gray-400 font-normal ml-1">(Mehrfachauswahl möglich)</span>
            </label>
            @error('schueler_ids')
                <p class="text-red-600 text-xs mb-2">{{ $message }}</p>
            @enderror

            {{-- Alle auswählen / Alle abwählen --}}
            <div class="flex gap-3 mb-2">
                <button type="button" onclick="document.querySelectorAll('input[name=\'schueler_ids[]\']:not([disabled])').forEach(cb => cb.checked = true)"
                        class="text-xs text-primary-600 hover:underline">Alle auswählen</button>
                <button type="button" onclick="document.querySelectorAll('input[name=\'schueler_ids[]\']').forEach(cb => cb.checked = false)"
                        class="text-xs text-gray-500 hover:underline">Alle abwählen</button>
            </div>

            <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-80 overflow-y-auto">
                @if($wpPlan->klasse)
                    {{-- Plan hat Klasse: Schüler direkt anzeigen --}}
                    @forelse($schueler as $s)
                        @php $vorhanden = in_array($s->id, $bereitsVorhanden); @endphp
                        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 {{ $vorhanden ? 'opacity-60' : '' }}">
                            <input type="checkbox" name="schueler_ids[]" value="{{ $s->id }}"
                                   class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <div class="flex-1 min-w-0">
                                <span class="text-sm font-medium text-gray-900">
                                    {{ $s->nachname }}, {{ $s->vorname }}
                                </span>
                                @if($vorhanden)
                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                                        Plan bereits vorhanden
                                    </span>
                                @endif
                            </div>
                        </label>
                    @empty
                        <p class="px-4 py-6 text-sm text-gray-400 text-center">
                            Keine Schüler/innen in dieser Klasse gefunden.
                        </p>
                    @endforelse
                @else
                    {{-- Plan ohne Klasse: Schüler nach Klasse gruppiert --}}
                    @forelse($klassen ?? [] as $klasse)
                        @php
                            $klasseSchueler = $schueler->where('klasse_id', $klasse->id)->values();
                        @endphp
                        @if($klasseSchueler->count() > 0)
                            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    {{ $klasse->name }}
                                </span>
                            </div>
                            @foreach($klasseSchueler as $s)
                                @php $vorhanden = in_array($s->id, $bereitsVorhanden); @endphp
                                <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-gray-50 {{ $vorhanden ? 'opacity-60' : '' }}">
                                    <input type="checkbox" name="schueler_ids[]" value="{{ $s->id }}"
                                           class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ $s->nachname }}, {{ $s->vorname }}
                                        </span>
                                        @if($vorhanden)
                                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                                                Plan bereits vorhanden
                                            </span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        @endif
                    @empty
                        <p class="px-4 py-6 text-sm text-gray-400 text-center">
                            Keine Schüler/innen gefunden.
                        </p>
                    @endforelse
                    @if(($schueler->whereNull('klasse_id')->count() ?? 0) > 0)
                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ohne Klasse</span>
                        </div>
                        @foreach($schueler->whereNull('klasse_id') as $s)
                            @php $vorhanden = in_array($s->id, $bereitsVorhanden); @endphp
                            <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-gray-50 {{ $vorhanden ? 'opacity-60' : '' }}">
                                <input type="checkbox" name="schueler_ids[]" value="{{ $s->id }}"
                                       class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ $s->nachname }}, {{ $s->vorname }}
                                    </span>
                                    @if($vorhanden)
                                        <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                                            Plan bereits vorhanden
                                        </span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    @endif
                @endif
            </div>
        </div>
        {{-- Formatvorlage --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Formatvorlage</label>
            <select name="formatvorlage_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">– Standard –</option>
                @foreach($formatvorlagen as $fv)
                    <option value="{{ $fv->id }}">{{ $fv->name }}</option>
                @endforeach
            </select>
        </div>
        {{-- Buttons --}}
        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="flex-1 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700">
                Kinderplan(e) erstellen
            </button>
            <a href="{{ route('wp.edit', $wpPlan) }}"
               class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                Abbrechen
            </a>
        </div>
    </form>
</div>
@endsection
@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

