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

    // Alle Kalender für das Frontend: OxCalendar + aktive iCal-Feeds
    $alleKalenderFrontend = $kalender->map(fn($c) => [
        'id'    => $c->id,
        'name'  => $c->name,
        'farbe' => $c->farbe,
        'typ'   => 'ox',
    ])->concat($icalFeeds->map(fn($f) => [
        'id'         => 'ical_' . $f->id,
        'name'       => $f->name,
        'farbe'      => $f->farbe ?? '#6366f1',
        'typ'        => 'ical',
        'delete_url' => route('calendar.ical.destroy', $f),
        'fehler'     => $f->fehler_meldung,
    ]))->values();

    $schreibbarFrontend = $schreibbareKalender->map(fn($c) => [
        'id'    => $c->id,
        'name'  => $c->name,
        'farbe' => $c->farbe,
    ])->values();

    // Nach Validierungsfehlern das Formular mit den eingegebenen Werten erneut öffnen
    $formularAlt = old('_formular') ? [
        'termin_id'    => old('_termin_id'),
        'updated_at'   => old('expected_updated_at'),
        'kalender_ids' => array_map('intval', (array) old('kalender_ids', [])),
        'titel'        => old('titel'),
        'beginn'       => old('beginn'),
        'ende'         => old('ende'),
        'ganztaegig'   => (bool) old('ganztaegig'),
        'ort'          => old('ort'),
        'beschreibung' => old('beschreibung'),
        'rrule'        => old('rrule'),
        'room_id'      => old('room_id'),
    ] : null;

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP;
@endphp
<div class="calendar-wrapper px-4 py-4 max-md:px-2 {{ $canCreate ? '' : 'calendar-no-create' }}"
     x-data="calendarApp"
     data-calendars='{!! json_encode($alleKalenderFrontend, $jsonFlags) !!}'
     data-writable-calendars='{!! json_encode($schreibbarFrontend, $jsonFlags) !!}'
     data-rooms='{!! json_encode($raeume, $jsonFlags) !!}'
     data-form-old='{!! json_encode($formularAlt, $jsonFlags) !!}'
     data-default-view="{{ $defaultView }}"
     data-can-create="{{ $canCreate ? 'true' : 'false' }}"
     data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
     data-can-book-rooms="{{ $canBookRooms ? 'true' : 'false' }}"
     data-import-error="{{ $errors->has('datei') ? 'true' : 'false' }}"
     data-user-colors='{!! json_encode($userColors, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) !!}'>

    {{-- ─── Seiten-Header ─────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-3 min-w-0">
            <button type="button"
                    @click="toggleSidebar()"
                    class="no-print inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300"
                    :aria-expanded="sidebarVisible.toString()"
                    title="Seitenleiste ein-/ausblenden">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <h1 class="text-2xl font-bold text-gray-900 leading-tight truncate">Kalender</h1>
        </div>

        <div class="no-print flex flex-wrap items-center gap-2">
            @if($canImport)
                <button type="button"
                        @click="showImportModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 hover:text-gray-900 text-sm rounded-md transition-colors"
                        title="Termine aus einer .ics-Datei importieren">
                    <i class="fas fa-file-import text-xs"></i>
                    Import
                </button>
            @endif
            <a :href="`{{ route('calendar.export.pdf') }}?date=${currentWeekDate()}&calendars=${pdfCalendarsParam || 'none'}`"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 hover:text-gray-900 text-sm rounded-md transition-colors"
               title="Wochenansicht als PDF herunterladen">
                <i class="fas fa-file-pdf text-xs"></i>
                PDF
            </a>
            @can('manage calendar')
                <a href="{{ route('calendar.admin') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-amber-50 hover:border-amber-300 hover:text-amber-800 text-sm rounded-md transition-colors"
                   title="Kalender-Verwaltung">
                    <i class="fas fa-cog text-xs"></i>
                    Verwaltung
                </a>
            @endcan
            @can('create calendar events')
                @if($schreibbareKalender->isNotEmpty())
                    <button type="button"
                            @click="openCreateModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <i class="fas fa-plus text-xs"></i>
                        Neuer Termin
                    </button>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-200 text-gray-500 text-sm font-medium rounded-md cursor-not-allowed"
                          title="Kein schreibbarer Kalender verfügbar.">
                        <i class="fas fa-plus text-xs"></i>
                        Neuer Termin
                    </span>
                @endif
            @endcan
        </div>
    </div>

    {{-- ─── Sync-Warnung ──────────────────────────────────────────────── --}}
    @if($syncVeraltet)
        <div class="no-print flex items-center gap-2 px-3 py-2 mb-3 bg-amber-50 border border-amber-300 rounded-md text-amber-800 text-sm">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                Kalender-Daten möglicherweise nicht aktuell. Letzte Synchronisation:
                {{ \Carbon\Carbon::parse($aeltesteSync)->diffForHumans() }}
            </span>
        </div>
    @endif

    {{-- ─── Layout: Sidebar + FullCalendar ───────────────────────────── --}}
    <div class="relative flex items-start gap-4">
        {{-- Mobile: Hintergrund hinter der ausgeklappten Sidebar --}}
        <div x-show="sidebarVisible && isMobile"
             x-cloak
             @click="sidebarVisible = false"
             class="no-print fixed inset-0 bg-black/30 z-[60] md:hidden"></div>

        @include('calendar.partials.filterSidebar')

        {{-- .cal-fc muss ein Vorfahre sein: FullCalendar setzt .fc auf das x-ref-Element selbst --}}
        <div class="cal-fc flex-1 min-w-0 bg-white border border-gray-200 rounded-lg p-3 max-md:p-2 shadow-sm">
            <div x-ref="calendarEl"></div>
        </div>
    </div>

    {{-- ─── Footer ────────────────────────────────────────────────────── --}}
    <p class="mt-3 text-sm text-gray-500">
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
    @if($canImport)
        @include('calendar.partials.importModal')
    @endif
    @if($canCreate || $canEdit)
        @include('calendar.partials.terminForm')
    @endif
</div>
@endsection

@push('js')
    <script>
        function copyFeedUrl() {
            const input = document.getElementById('ical-feed-url');
            if (!input) return;

            const showSuccess = () => {
                const btn = input.nextElementSibling;
                if (!btn) return;
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                setTimeout(() => { btn.innerHTML = original; }, 2000);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value)
                    .then(showSuccess)
                    .catch(() => { input.select(); document.execCommand('copy'); showSuccess(); });
            } else {
                input.select();
                input.setSelectionRange(0, 99999);
                try { document.execCommand('copy'); } catch (_) {}
                showSuccess();
            }
        }
    </script>
@endpush
