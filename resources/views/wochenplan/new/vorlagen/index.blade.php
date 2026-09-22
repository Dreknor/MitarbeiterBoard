@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-container p-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Vorlagen</h1>
        <a href="{{ route('wp.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Übersicht</a>
    </div>

    @if(session('Meldung'))
        <div class="mb-4 px-4 py-3 rounded-lg {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
            {{ session('Meldung') }}
        </div>
    @endif

    @if($vorlagen->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg mb-2">📄 Keine Vorlagen vorhanden.</p>
            <p class="text-sm">Öffne einen Plan und speichere ihn als Vorlage, um ihn hier zu sehen.</p>
            <a href="{{ route('wp.index') }}"
               class="mt-4 inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                Zur Planübersicht
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($vorlagen as $vorlage)
                @php
                    $aufgabenAnzahl = $vorlage->planFaecher->sum(fn($pf) => $pf->aufgaben->count());
                    $erstelltVon = $vorlage->creator?->name ?? '–';
                @endphp
                <div class="bg-white rounded-lg border border-gray-200 p-4 flex flex-col gap-3">
                    {{-- Kopf --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                Vorlage
                            </span>
                        </div>
                        <h3 class="font-semibold text-gray-900">{{ $vorlage->vorlage_name ?? $vorlage->name }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $vorlage->planFaecher->count() }} Fächer
                            · {{ $aufgabenAnzahl }} Aufgaben
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Erstellt: {{ $vorlage->created_at?->format('d.m.Y') ?? '–' }}
                            @if($erstelltVon !== '–') von {{ $erstelltVon }} @endif
                        </p>
                    </div>

                    {{-- Aktionen --}}
                    <div class="flex gap-2 mt-auto">
                        {{-- Neuen Plan aus Vorlage erstellen --}}
                        <a href="{{ route('wp.create') }}?vorlage_id={{ $vorlage->id }}"
                           class="flex-1 text-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700">
                            + Neuen Plan erstellen
                        </a>

                        {{-- Bearbeiten --}}
                        <a href="{{ route('wp.edit', $vorlage) }}"
                           class="px-3 py-1.5 bg-primary-50 text-primary-700 text-xs font-medium rounded-md hover:bg-primary-100">
                            Bearbeiten
                        </a>

                        {{-- Löschen --}}
                        <form method="POST" action="{{ route('wp.vorlagen.destroy', $vorlage) }}"
                              onsubmit="return confirm('Vorlage wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 text-xs rounded-md hover:bg-red-100">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush
