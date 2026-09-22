@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('content')
<div class="personal-wrapper" x-data="personalTabs('uebersicht')" x-init="init()" x-cloak>

    {{-- Seitenkopf --}}
    <div class="flex items-center gap-4 mb-6">
        <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xl">
            {{ substr(auth()->user()->name, 0, 1) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mein Profil</h1>
            <p class="text-gray-500 text-sm">{{ auth()->user()->email }}</p>
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- Tab-Navigation --}}
    <div class="flex border-b border-gray-200 mb-6 overflow-x-auto">
        @foreach([
            ['key' => 'uebersicht',      'label' => 'Übersicht'],
            ['key' => 'vertraege',       'label' => 'Verträge'],
            ['key' => 'urlaub',          'label' => 'Urlaub & Abwesenheiten'],
            ['key' => 'dokumente',       'label' => 'Dokumente'],
            ['key' => 'qualifikationen', 'label' => 'Qualifikationen'],
            ['key' => 'gespraeche',      'label' => 'Gespräche'],
            ['key' => 'einwilligungen',  'label' => 'Einwilligungen'],
        ] as $tab)
        <button @click="setTab('{{ $tab['key'] }}')"
                :class="isActive('{{ $tab['key'] }}') ? 'personal-tab personal-tab-active' : 'personal-tab personal-tab-inactive'">
            {{ $tab['label'] }}
        </button>
        @endforeach
    </div>

    {{-- Tab: Übersicht --}}
    <div x-show="isActive('uebersicht')">
        @include('personal.self-service._tab_uebersicht')
    </div>

    {{-- Tab: Verträge --}}
    <div x-show="isActive('vertraege')">
        @include('personal.self-service._tab_vertraege')
    </div>

    {{-- Tab: Urlaub --}}
    <div x-show="isActive('urlaub')">
        <div class="personal-card text-center text-gray-400 py-12">
            Urlaubsübersicht – verfügbar über das Urlaubsmodul
            <br><a href="{{ route('holidays.index') }}" class="text-blue-600 hover:underline mt-2 inline-block">Zum Urlaubsmodul →</a>
        </div>
    </div>

    {{-- Tab: Dokumente --}}
    <div x-show="isActive('dokumente')">
        <div class="personal-card text-center text-gray-400 py-12">
            Dokumentenverwaltung wird in Phase 2 verfügbar.
        </div>
    </div>

    {{-- Tab: Qualifikationen --}}
    <div x-show="isActive('qualifikationen')">
        <div class="personal-card text-center text-gray-400 py-12">
            Qualifikationsverwaltung wird in Phase 2 verfügbar.
        </div>
    </div>

    {{-- Tab: Gespräche --}}
    <div x-show="isActive('gespraeche')">
        <div class="personal-card text-center text-gray-400 py-12">
            Mitarbeitergespräche werden in Phase 3 verfügbar.
        </div>
    </div>

    {{-- Tab: Einwilligungen --}}
    <div x-show="isActive('einwilligungen')">
        @include('personal.self-service._tab_einwilligungen')
    </div>

</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush

