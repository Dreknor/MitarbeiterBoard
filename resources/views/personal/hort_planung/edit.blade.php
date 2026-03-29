@extends('layouts.app')

@section('title', 'Planung bearbeiten – ' . $planung->name)
@section('site-title', 'Hortstunden-Planung')

@push('css')
    @vite(['resources/css/hort-planung.css'])
@endpush

@section('content')
<div class="hort-planung-wrapper">
<div class="px-4 py-6">

    {{-- Header: Breadcrumb + Aktionen --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <nav class="flex items-center gap-1.5 text-sm text-gray-500 min-w-0">
            <a href="{{ route('hort-planung.index') }}" class="hover:text-blue-600 shrink-0">Hortstunden-Planung</a>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <a href="{{ route('hort-planung.show', $planung) }}" class="hover:text-blue-600 truncate">{{ $planung->name }}</a>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-gray-800 font-medium shrink-0">Einstellungen</span>
        </nav>
        <a href="{{ route('hort-planung.create') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Neue Planung
        </a>
    </div>

    {{-- Flash-Meldung --}}
    @if(session('Meldung'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium
                {{ session('type') === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' :
                   (session('type') === 'danger'  ? 'bg-red-50 text-red-800 border border-red-200' :
                    'bg-blue-50 text-blue-800 border border-blue-200') }}"
         role="alert">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- Metadaten-Leiste (volle Breite) --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Planungsinfo</h2>
        </div>
        <div class="px-5 py-3 flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div class="flex items-center gap-1.5">
                <span class="text-gray-400 shrink-0">Abteilung</span>
                <span class="font-medium text-gray-800">{{ $planung->department?->name ?? '–' }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-gray-400 shrink-0">Zeitraum</span>
                <span class="font-medium text-gray-800">{{ $planung->start_monat->format('M Y') }} – {{ $planung->end_monat->format('M Y') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-gray-400 shrink-0">Monate</span>
                <span class="font-medium text-gray-800">{{ $planung->monate->count() }} generiert</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-gray-400 shrink-0">Typ</span>
                <span class="font-medium text-gray-800">{{ $planung->typ === 'rueckblick' ? '📊 Rückblick' : '📋 Planung' }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-gray-400 shrink-0">Erstellt von</span>
                <span class="font-medium text-gray-800">{{ $planung->ersteller?->name ?? '–' }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-gray-400 shrink-0">am</span>
                <span class="font-medium text-gray-800">{{ $planung->created_at->format('d.m.Y') }}</span>
            </div>
            @if($planung->kopiert_von_id)
            <div class="flex items-center gap-1.5">
                <span class="text-gray-400 shrink-0">Kopie von</span>
                <span class="font-medium text-amber-600">{{ $planung->kopiertvon?->name }}</span>
            </div>
            @endif
            @if($planung->aktiv)
            <span class="badge-aktiv">✓ aktiv</span>
            @endif
        </div>
    </div>

    {{-- Hauptinhalt: 2 gleichbreite Spalten --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Linke Spalte: Einstellungen + Gefahrenzone --}}
        <div class="space-y-5">

            {{-- Einstellungen-Formular --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Einstellungen</h2>
                </div>
                <form action="{{ route('hort-planung.update', $planung) }}" method="POST" class="p-5 space-y-4">
                    @csrf @method('PUT')

                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" value="{{ old('name', $planung->name) }}"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-400 @enderror">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Beschreibung --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                        <textarea name="beschreibung" rows="3"
                                  class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none">{{ old('beschreibung', $planung->beschreibung) }}</textarea>
                    </div>

                    {{-- Planungszeitraum --}}
                    <div class="pt-3 border-t border-gray-100">
                        <label class="block text-sm font-medium text-gray-700 mb-0.5">Planungszeitraum</label>
                        <p class="text-xs text-gray-400 mb-2">
                            Fehlende Monate werden automatisch ergänzt. Bestehende Daten bleiben erhalten.
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Startmonat</label>
                                <input type="month" name="start_monat"
                                       value="{{ old('start_monat', $planung->start_monat->format('Y-m')) }}"
                                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('start_monat') border-red-400 @enderror">
                                @error('start_monat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Endmonat</label>
                                <input type="month" name="end_monat"
                                       value="{{ old('end_monat', $planung->end_monat->format('Y-m')) }}"
                                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('end_monat') border-red-400 @enderror">
                                @error('end_monat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Aktiv --}}
                    <div class="pt-3 border-t border-gray-100">
                        <label class="flex items-center gap-2.5 cursor-pointer group">
                            <input type="hidden" name="aktiv" value="0">
                            <input type="checkbox" name="aktiv" value="1"
                                   {{ old('aktiv', $planung->aktiv) ? 'checked' : '' }}
                                   class="w-4 h-4 accent-blue-600">
                            <div>
                                <span class="text-sm font-medium text-gray-700 group-hover:text-blue-600">Als aktive Planung markieren</span>
                                <p class="text-xs text-gray-400">Deaktiviert automatisch andere aktive Planungen dieser Abteilung.</p>
                            </div>
                        </label>
                    </div>

                    <button type="submit"
                            class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-sm">
                        Einstellungen speichern
                    </button>
                </form>
            </div>

            {{-- Danger Zone --}}
            <div class="bg-white rounded-2xl border border-red-200 shadow-sm overflow-hidden"
                 x-data="{ confirmDelete: false }">
                <div class="px-5 py-3.5 border-b border-red-100 bg-red-50">
                    <h2 class="text-sm font-semibold text-red-700 uppercase tracking-wide">Gefahrenzone</h2>
                </div>
                <div class="p-5">
                    <p class="text-sm text-gray-600 mb-3">
                        Die Planung wird als gelöscht markiert und aus der Übersicht entfernt.
                        Alle Daten bleiben erhalten (SoftDelete).
                    </p>
                    <button @click="confirmDelete = true" x-show="!confirmDelete"
                            class="w-full py-2 border border-red-300 text-red-600 hover:bg-red-50 font-medium rounded-xl text-sm">
                        Planung löschen
                    </button>
                    <div x-show="confirmDelete" x-cloak class="space-y-2">
                        <p class="text-sm font-medium text-red-700">Wirklich löschen?</p>
                        <form action="{{ route('hort-planung.destroy', $planung) }}" method="POST" class="flex gap-2">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="flex-1 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl text-sm">
                                Ja, löschen
                            </button>
                            <button type="button" @click="confirmDelete = false"
                                    class="flex-1 py-2 bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 font-medium rounded-xl text-sm">
                                Abbrechen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rechte Spalte: Faktoren + Zusatzstunden --}}
        <div class="space-y-5">
            @include('personal.hort_planung._faktoren', ['planung' => $planung])
            @include('personal.hort_planung._zusatztypen', ['planung' => $planung])
        </div>

    </div>
</div>
</div>
@endsection

