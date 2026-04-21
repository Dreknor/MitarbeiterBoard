@extends('layouts.app')

@push('css')
    @vite(['resources/css/dashboard.css'])
@endpush

@section('content')
<div id="dashboard-v2-root" class="dashboard-wrapper" x-data="dashboardApp({{ \Illuminate\Support\Js::from($cardsJson) }})" x-cloak>

    {{-- Safelist: damit Tailwind diese responsive Klassen in's CSS aufnimmt (werden dynamisch via widthClass() in dashboard.js gesetzt) --}}
    <span class="hidden col-span-1 col-span-2 col-span-3 col-span-full md:col-span-1 md:col-span-2 md:col-span-3 md:col-span-full lg:col-span-1 lg:col-span-2 lg:col-span-3 lg:col-span-full"></span>

    {{-- ─── Header ──────────────────────────────────────────────────────── --}}
    <div class="px-4 pt-4 pb-2">
        <div class="flex flex-wrap items-start justify-between gap-3">

            {{-- Begrüßung & Datum --}}
            <div>
                <h1 class="text-xl font-bold text-gray-800">
                    @php
                        $hour = now()->hour;
                        if ($hour < 12) $greeting = 'Guten Morgen';
                        elseif ($hour < 18) $greeting = 'Guten Tag';
                        else $greeting = 'Guten Abend';
                    @endphp
                    {{ $greeting }}, {{ auth()->user()->vorname ?? auth()->user()->name }}!
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ now()->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                </p>
            </div>

            {{-- Aktions-Buttons --}}
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard.hilfe') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50 no-underline">
                    <i class="fas fa-question-circle opacity-70"></i>
                    Hilfe
                </a>
                <button @click="toggleEditMode()"
                        :class="editMode ? 'bg-amber-50 border-amber-300 text-amber-700' : 'bg-white border-gray-200 text-gray-600'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border rounded-lg shadow-sm hover:opacity-80">
                    <i class="fas fa-edit opacity-70"></i>
                    Anpassen
                </button>
                <button @click="showSettings = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50">
                    <i class="fas fa-cog opacity-70"></i>
                    Einstellungen
                </button>
            </div>
        </div>

        {{-- Edit-Modus Hinweis --}}
        <div x-show="editMode" x-cloak
             class="mt-3 flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
            <i class="fas fa-info-circle"></i>
            Karten verschieben und Größen anpassen. Klicke auf <strong>Speichern</strong> um die Änderungen zu übernehmen.
            <button @click="saveLayout()" class="ml-auto px-3 py-1 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600">
                Speichern
            </button>
        </div>
    </div>

    {{-- ─── Dashboard-Grid ─────────────────────────────────────────────── --}}
    {{-- @resize-card / @toggle-card werden von cardWrapper via $dispatch nach oben gebubbled --}}
    <div x-ref="grid" class="dashboard-grid px-4 pb-6"
         @resize-card.window="resizeCard($event.detail.id, $event.detail.width)"
         @toggle-card.window="toggleCard($event.detail.id)">
        @foreach($cards->filter(fn($c) => $c->active)->sortBy('order') as $card)
            {{-- :class reaktiv über cards-Array – reagiert auf resizeCard() --}}
            {{-- x-show: versteckt Grid-Slot wenn Card ausgeblendet ODER leer (außer im Edit-Modus) --}}
            <div data-card-id="{{ $card->id }}"
                 :class="widthClass((cards.find(c => c.id === {{ $card->id }}) || {}).width || 'md')"
                 x-data="{ loaded: false, html: '', error: false, isEmpty: false }"
                 x-show="((cards.find(c => c.id === {{ $card->id }}) || {active: true}).active) && (!loaded || !isEmpty || editMode)"
                 x-intersect.once="
                     fetch('/dashboard/card/{{ $card->id }}', {
                         headers: { 'X-Requested-With': 'XMLHttpRequest' }
                     })
                     .then(r => { if (!r.ok) throw r; return r.text() })
                     .then(h => {
                         html = h;
                         const tmp = document.createElement('div');
                         tmp.innerHTML = h;
                         // Expliziter Marker hat Vorrang – Empty-State-Wrapper setzen data-card-empty='true'
                         const explicitEmpty = tmp.querySelector('[data-card-empty=\'true\']') !== null;
                         if (explicitEmpty) {
                             isEmpty = true;
                         } else {
                             const hasText = tmp.textContent.replace(/\s+/g, '').length > 0;
                             const hasInteractive = tmp.querySelectorAll('a, button, form, input, select, img, svg, table, ul, ol').length > 0;
                             isEmpty = !hasText && !hasInteractive;
                         }
                         loaded = true;
                     })
                     .catch(() => { error = true; loaded = true })
                 ">
                @include('dashboard.cardWrapper', ['card' => $card])
            </div>
        @endforeach
    </div>

    {{-- ─── Einstellungs-Panel ─────────────────────────────────────────── --}}
    @include('dashboard.settingsPanel')

</div>
@endsection

@push('js')
    @vite(['resources/js/dashboard.js'])
@endpush

