@extends('layouts.app')

@push('css')
    @vite('resources/css/calendar.css')
@endpush

@push('js')
    @vite('resources/js/calendar.js')
@endpush

@section('content')
@php
    $aeltesteSync = $kalender->min('letzte_synchronisation');
    $syncVeraltet = $aeltesteSync && \Carbon\Carbon::parse($aeltesteSync)->lt(now()->subHour());
@endphp
<div class="px-4 py-4 {{ $canCreate ? '' : 'calendar-no-create' }}"
     x-data="calendarApp"
     data-calendars='{!! json_encode($kalender->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'farbe' => $c->farbe]), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) !!}'
     data-default-view="{{ $defaultView }}"
     data-can-create="{{ $canCreate ? 'true' : 'false' }}"
     data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
     data-user-colors='{!! json_encode($userColors, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) !!}'>

    {{-- ─── Seiten-Header ─────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            {{-- Sidebar-Toggle --}}
            <button type="button"
                    @click="toggleSidebar()"
                    class="no-print inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300"
                    title="Kalender-Liste ein-/ausblenden">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <h1 class="text-2xl font-bold text-gray-900">📅 Kalender</h1>
        </div>

        <div class="flex items-center gap-2">
            {{-- PDF-Export (TODO 28) --}}
            @can('view calendar')
                <a :href="`/calendar/export/pdf?date=${currentWeekDate()}`"
                   class="no-print inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-green-50 border border-gray-300 hover:border-green-300 text-gray-600 hover:text-green-700 text-sm rounded-md transition-colors"
                   title="Wochenansicht als PDF herunterladen">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </a>
            @endcan
            @can('manage calendar')
                <a href="{{ route('calendar.admin') }}"
                   class="no-print inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-amber-50 border border-gray-300 hover:border-amber-300 text-gray-600 hover:text-amber-700 text-sm rounded-md transition-colors"
                   title="Kalender-Verwaltung">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Verwaltung
                </a>
            @endcan
            @can('create calendar events')
                @if($schreibbareKalender->isNotEmpty())
                    <button type="button"
                            @click="showCreateModal = true"
                            class="no-print inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Neuer Termin
                    </button>
                @else
                    <span class="no-print inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-200 text-gray-500 text-sm font-medium rounded-md cursor-not-allowed"
                          title="Kein schreibbarer Kalender verfügbar.">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Neuer Termin
                    </span>
                @endif
            @endcan
        </div>
    </div>

    {{-- ─── Sync-Warnung ──────────────────────────────────────────────── --}}
    @if($syncVeraltet)
        <div class="no-print flex items-center gap-2 px-3 py-2 mb-3 bg-amber-50 border border-amber-400 rounded-md text-amber-800 text-sm">
            ⚠️ Kalender-Daten möglicherweise nicht aktuell. Letzte Synchronisation:
            {{ \Carbon\Carbon::parse($aeltesteSync)->diffForHumans() }}
        </div>
    @endif

    {{-- ─── Layout: Sidebar + FullCalendar ───────────────────────────── --}}
    <div class="flex items-start relative">
        @include('calendar.partials.filterSidebar')
        <div class="flex-1 min-w-0 cal-fc" x-ref="calendarEl"></div>
    </div>

    {{-- ─── Footer ────────────────────────────────────────────────────── --}}
    <p class="mt-3 text-sm text-gray-400">
        @if($kalender->isNotEmpty() && $kalender->max('letzte_synchronisation'))
            Zuletzt synchronisiert:
            {{ \Carbon\Carbon::parse($kalender->max('letzte_synchronisation'))->diffForHumans() }}
        @else
            Noch nicht synchronisiert.
        @endif
    </p>

    {{-- Modals --}}
    @include('calendar.partials.terminModal')
    @include('calendar.partials.icalFeedModal')
    @can('create calendar events')
        @include('calendar.partials.terminForm')
    @endcan
</div>
@endsection

@push('js')
    <script>
        function copyFeedUrl() {
            const input = document.getElementById('ical-feed-url');
            if (!input) return;
            navigator.clipboard.writeText(input.value).then(() => {
                const btn = input.nextElementSibling;
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                setTimeout(() => { btn.innerHTML = original; }, 2000);
            }).catch(() => { input.select(); document.execCommand('copy'); });
        }
    </script>
@endpush

