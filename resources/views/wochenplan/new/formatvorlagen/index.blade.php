@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-container p-4 max-w-4xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('wp.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Übersicht</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Formatvorlagen</h1>
        </div>
        <a href="{{ route('wp.formatvorlagen.create') }}"
           class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
            + Neue Formatvorlage
        </a>
    </div>

    @if(session('Meldung'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm
            {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
            {{ session('Meldung') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($formatvorlagen as $fv)
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">📄</span>
                        <h3 class="font-semibold text-gray-900">{{ $fv->name }}</h3>
                        @if($fv->is_default)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                                ⭐ Standard
                            </span>
                        @endif
                    </div>
                </div>

                @if($fv->beschreibung)
                    <p class="text-xs text-gray-500 mb-2">{{ $fv->beschreibung }}</p>
                @endif

                <div class="text-xs text-gray-500 space-y-0.5 mb-3">
                    <div>Schrift: {{ ucfirst($fv->schriftgroesse) }} · {{ $fv->schriftart ?? 'Arial' }}</div>
                    <div>Template: <code class="bg-gray-100 px-1 rounded">{{ $fv->blade_template }}</code></div>
                    <div>Verwendet in: <strong>{{ $fv->plaene_count }}</strong> {{ $fv->plaene_count === 1 ? 'Plan' : 'Plänen' }}</div>
                </div>

                <div class="flex gap-2 pt-2 border-t border-gray-100">
                    <a href="{{ route('wp.formatvorlagen.edit', $fv) }}"
                       class="inline-flex items-center px-3 py-1.5 text-xs bg-primary-50 text-primary-700 rounded-md hover:bg-primary-100">
                        ✏️ Bearbeiten
                    </a>
                    <a href="{{ route('wp.formatvorlagen.vorschau', $fv) }}" target="_blank"
                       class="inline-flex items-center px-3 py-1.5 text-xs bg-gray-50 text-gray-700 rounded-md hover:bg-gray-100">
                        👁️ Vorschau
                    </a>
                    @if($fv->plaene_count === 0 && !$fv->is_default)
                        <form method="POST" action="{{ route('wp.formatvorlagen.destroy', $fv) }}"
                              onsubmit="return confirm('Formatvorlage wirklich löschen?')" class="ml-auto">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-1.5 text-xs text-red-600 border border-red-200 rounded-md hover:bg-red-50">
                                🗑️ Löschen
                            </button>
                        </form>
                    @else
                        <span class="ml-auto inline-flex items-center px-3 py-1.5 text-xs text-gray-300 border border-gray-100 rounded-md cursor-not-allowed"
                              title="{{ $fv->is_default ? 'Standard-Vorlage kann nicht gelöscht werden' : 'Wird in Plänen verwendet' }}">
                            🗑️ Löschen
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-2 text-center py-12 text-gray-400">
                <p class="text-lg mb-2">📄</p>
                <p class="text-sm">Noch keine Formatvorlagen vorhanden.</p>
                <a href="{{ route('wp.formatvorlagen.create') }}" class="mt-3 inline-block text-primary-600 hover:underline text-sm">
                    Erste Formatvorlage erstellen
                </a>
            </div>
        @endforelse
    </div>

</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

