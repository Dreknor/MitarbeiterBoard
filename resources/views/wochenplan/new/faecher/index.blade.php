@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-container p-4 max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Fächer-Katalog</h1>
        <a href="{{ route('wp.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Übersicht</a>
    </div>

    @if(session('Meldung'))
        <div class="mb-4 px-4 py-3 rounded-lg {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
            {{ session('Meldung') }}
        </div>
    @endif

    {{-- Neues Fach --}}
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Neues Fach hinzufügen</h2>
        <form method="POST" action="{{ route('wp.faecher.store') }}" class="flex gap-3">
            @csrf
            <input type="text" name="name" placeholder="Fachname" required
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <label class="flex items-center gap-1.5 text-sm">
                <input type="checkbox" name="is_default" value="1" class="rounded">
                Standard
            </label>
            <button type="submit"
                    class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                Hinzufügen
            </button>
        </form>
    </div>

    {{-- Fächerliste --}}
    <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        @forelse($faecher as $fach)
            <div class="flex items-center justify-between p-3">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-800">{{ $fach->name }}</span>
                    @if($fach->is_default)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Standard</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('wp.faecher.destroy', $fach) }}"
                      onsubmit="return confirm('Fach löschen?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Löschen</button>
                </form>
            </div>
        @empty
            <p class="text-gray-400 text-sm text-center py-6">Keine Fächer vorhanden.</p>
        @endforelse
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

