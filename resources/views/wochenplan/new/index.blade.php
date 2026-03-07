@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-container p-4">

    {{-- Flash-Meldungen --}}
    @if(session('Meldung'))
        <div class="mb-4 px-4 py-3 rounded-lg {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
            {{ session('Meldung') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Wochenpläne</h1>
        @canany(['create wochenplan', 'create Wochenplan'])
            <div class="flex items-center gap-2">
                <a href="{{ route('wp.vorlagen.index') }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    📄 Vorlagen
                </a>
                <a href="{{ route('wp.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Neuer Plan
                </a>
            </div>
        @endcanany
    </div>

    {{-- Filter & Tabs --}}
    <div x-data="{ tab: '{{ request('tab') === 'vorlagen' ? 'vorlagen' : 'aktuelle' }}' }" class="mb-6">
        <form method="GET" action="{{ route('wp.index') }}" class="flex flex-wrap gap-3 mb-4">
            <select name="klasse_id" onchange="this.form.submit()"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">Alle Klassen</option>
                @foreach($klassen as $klasse)
                    <option value="{{ $klasse->id }}" {{ $filterKlasseId == $klasse->id ? 'selected' : '' }}>
                        {{ $klasse->name }}
                    </option>
                @endforeach
            </select>

            <select name="typ" onchange="this.form.submit()"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="alle" {{ $filterTyp === 'alle' ? 'selected' : '' }}>Alle Typen</option>
                <option value="klassenplan" {{ $filterTyp === 'klassenplan' ? 'selected' : '' }}>Klassenpläne</option>
                <option value="schuelerplan" {{ $filterTyp === 'schuelerplan' ? 'selected' : '' }}>Schülerpläne</option>
            </select>

            <select name="zeitraum" onchange="this.form.submit()"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="alle" {{ $filterZeitraum === 'alle' ? 'selected' : '' }}>Alle Zeiträume</option>
                <option value="aktuell" {{ $filterZeitraum === 'aktuell' ? 'selected' : '' }}>Aktuelle</option>
                <option value="vergangen" {{ $filterZeitraum === 'vergangen' ? 'selected' : '' }}>Vergangene</option>
            </select>
        </form>

        {{-- Tabs --}}
        <div class="border-b border-gray-200 mb-4">
            <nav class="-mb-px flex space-x-6">
                <button @click="tab = 'aktuelle'"
                        :class="tab === 'aktuelle' ? 'border-b-2 border-primary-600 text-primary-600 font-medium' : 'text-gray-500 hover:text-gray-700'"
                        class="py-2 px-1 text-sm transition-colors">
                    Alle Pläne ({{ $plaene->total() }})
                </button>
                @canany(['create wochenplan', 'create Wochenplan'])
                <button @click="tab = 'vorlagen'"
                        :class="tab === 'vorlagen' ? 'border-b-2 border-primary-600 text-primary-600 font-medium' : 'text-gray-500 hover:text-gray-700'"
                        class="py-2 px-1 text-sm transition-colors">
                    Vorlagen ({{ $vorlagen->count() }})
                </button>
                @endcanany
            </nav>
        </div>

        {{-- Pläne Tab --}}
        <div x-show="tab === 'aktuelle'">
            @if($plaene->isEmpty())
                <div class="text-center py-16 text-gray-400">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-lg font-medium text-gray-500 mb-2">Noch keine Pläne vorhanden</p>
                    @canany(['create wochenplan', 'create Wochenplan'])
                        <p class="text-sm mb-4">Erstelle den ersten Wochenplan oder nutze eine Vorlage.</p>
                        <div class="flex justify-center gap-3">
                            <a href="{{ route('wp.create') }}"
                               class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Neuer Plan
                            </a>
                            @if($vorlagen->count() > 0)
                                <button @click="tab = 'vorlagen'"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                                    📄 Aus Vorlage
                                </button>
                            @endif
                        </div>
                    @else
                        <p class="text-sm">Es wurden noch keine Pläne erstellt.</p>
                    @endcanany
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($plaene as $plan)
                        @include('wochenplan.new.components.plan-card', ['plan' => $plan])
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Vorlagen Tab --}}
        @canany(['create wochenplan', 'create Wochenplan'])
        <div x-show="tab === 'vorlagen'" x-cloak>
            @if($vorlagen->isEmpty())
                <div class="text-center py-16 text-gray-400">
                    <p class="text-lg font-medium text-gray-500 mb-2">📄 Keine Vorlagen vorhanden</p>
                    <p class="text-sm mb-4">Öffne einen bestehenden Plan und speichere ihn als Vorlage.</p>
                    <a href="{{ route('wp.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Neuen Plan erstellen
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($vorlagen as $plan)
                        @include('wochenplan.new.components.plan-card', ['plan' => $plan])
                    @endforeach
                </div>
            @endif
        </div>
        @endcanany
    </div>

    {{-- Pagination --}}
    @if($plaene->hasPages())
        <div class="mt-4">
            {{ $plaene->links() }}
        </div>
    @endif

</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

