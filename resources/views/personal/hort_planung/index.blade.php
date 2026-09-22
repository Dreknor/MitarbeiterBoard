@extends('layouts.app')

@section('title', 'Hortstunden-Planung')
@section('site-title', 'Hortstunden-Planung')

@push('css')
    @vite(['resources/css/hort-planung.css'])
@endpush

@section('content')
<div class="hort-planung-wrapper">
<div class="max-w-6xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Hortstunden-Planung</h1>
            <p class="text-sm text-gray-500 mt-0.5">Strategische Personalstundenplanung für den Hort</p>
        </div>
        @can('manage hort planung')
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('hort-planung.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Neue Planung
            </a>
            <button onclick="document.getElementById('importModal').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-xl shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Excel-Import
            </button>
        </div>
        @endcan
    </div>

    {{-- Filter --}}
    <div class="flex gap-2 mb-5" x-data="{ filter: 'alle' }">
        @foreach(['alle' => 'Alle', 'planung' => '📋 Planungen', 'rueckblick' => '📊 Rückblicke'] as $val => $label)
        <button @click="filter = '{{ $val }}'"
                :class="filter === '{{ $val }}' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                x-on:click="
                    document.querySelectorAll('[data-typ]').forEach(el => {
                        el.style.display = ('{{ $val }}' === 'alle' || el.dataset.typ === '{{ $val }}') ? '' : 'none';
                    })
                ">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Planungsliste --}}
    @forelse($planungen as $planung)
    <div class="planning-card bg-white border border-gray-200 rounded-2xl shadow-sm mb-4 overflow-hidden transition-shadow"
         data-typ="{{ $planung->typ }}">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4 p-5">

            {{-- Icon + Infos --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="text-lg">{{ $planung->typ === 'rueckblick' ? '📊' : '📋' }}</span>
                    <h2 class="text-base font-semibold text-gray-800 truncate">{{ $planung->name }}</h2>

                    @if($planung->aktiv)
                        <span class="badge-aktiv">✓ aktiv</span>
                    @endif

                    <span class="{{ $planung->typ === 'rueckblick' ? 'badge-rueckblick' : 'badge-planung' }}">
                        {{ $planung->typ === 'rueckblick' ? 'Rückblick' : 'Planung' }}
                    </span>

                    @if($planung->kopiert_von_id)
                        <span class="badge-kopie">↳ Kopie von: {{ $planung->kopiertvon?->name ?? '–' }}</span>
                    @endif
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 mt-1">
                    <span>
                        <svg class="w-3.5 h-3.5 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        {{ $planung->start_monat->format('M Y') }} – {{ $planung->end_monat->format('M Y') }}
                    </span>
                    <span>{{ $planung->monate_count }} Monate</span>
                    @if($planung->department)
                        <span>{{ $planung->department->name }}</span>
                    @endif
                    <span class="text-gray-400">Geänd.: {{ $planung->updated_at->format('d.m.Y') }}</span>
                </div>

                @if($planung->beschreibung)
                    <p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ $planung->beschreibung }}</p>
                @endif
            </div>

            {{-- Aktionen --}}
            <div class="flex flex-wrap gap-2 shrink-0 sm:flex-col sm:items-end">
                <a href="{{ route('hort-planung.show', $planung) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-medium rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Anzeigen
                </a>
                <a href="{{ route('hort-planung.rueckblick', $planung) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-medium rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Rückblick
                </a>
                <a href="{{ route('hort-planung.export', $planung) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-50 hover:bg-green-100 text-green-700 text-xs font-medium rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export
                </a>
                @can('manage hort planung')
                <a href="{{ route('hort-planung.edit', $planung) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-medium rounded-lg border border-gray-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Bearbeiten
                </a>
                <button onclick="openDuplicateModal({{ $planung->id }}, '{{ addslashes($planung->name) }}')"
                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-medium rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Duplizieren
                </button>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-16 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm font-medium">Noch keine Planungen vorhanden.</p>
        @can('manage hort planung')
        <a href="{{ route('hort-planung.create') }}" class="mt-3 inline-flex items-center gap-1 text-blue-600 hover:underline text-sm">
            Erste Planung erstellen →
        </a>
        @endcan
    </div>
    @endforelse

</div>
</div>

{{-- Duplicate Modal --}}
@can('manage hort planung')
    @include('personal.hort_planung._duplicate_modal')
@endcan

{{-- Import Modal --}}
@can('manage hort planung')
    @include('personal.hort_planung._import_modal')
@endcan

@push('js')
<script>
function openDuplicateModal(planungId, planungName) {
    document.getElementById('duplicateForm').action = '/hort-planung/' + planungId + '/duplicate';
    document.getElementById('duplicateSourceName').textContent = planungName;
    document.getElementById('duplicateName').value = planungName + ' (Kopie)';
    document.getElementById('duplicateModal').classList.remove('hidden');
}
</script>
@endpush
@endsection

