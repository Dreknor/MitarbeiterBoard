@extends('layouts.app')

@section('title', 'Planung: ' . $planung->name)
@section('site-title', 'Hortstunden-Planung')

@push('css')
    @vite(['resources/css/hort-planung.css'])
@endpush

@section('content')
<div class="hort-planung-wrapper">

@php
    $canManage      = auth()->user()->can('manage hort planung');
    $monatsListe    = $planung->monate->sortBy('monat')->values();
    $aktiveFaktoren = $planung->faktoren->sortBy('position')->filter(fn($f) => $f->aktiv)->values();
    $aktiveZusatz   = $planung->zusatzstundenTypen->sortBy('position')->filter(fn($t) => $t->aktiv)->values();
    $heuteMk        = now()->format('Y-m');

    // ── Alpine initData ──────────────────────────────────────────────
    $initMonatData   = [];
    $initPersonData  = [];
    $initZusatzData  = [];
    $initBerechnungen = [];

    foreach ($monatsListe as $m) {
        $mk = $m->monat->format('Y-m');

        $initMonatData[$mk] = [
            'id'             => $m->id,
            'kinderanzahl'   => (int)   $m->kinderanzahl,
            'vollzeitstunden'=> (float) $m->vollzeitstunden,
            'notiz'          => $m->notiz,
        ];

        $initBerechnungen[$mk] = $berechnungenNachMonat->get($mk) ?? [];

        // Personen-Daten indexiert nach "mk_userId"
        $personenIdx = $m->personen->keyBy('user_id');
        $monatStart  = $m->monat;
        $monatEnde   = $m->monat->copy()->endOfMonth();

        foreach ($allePersonen as $ap) {
            $uid = $ap->user_id;
            $e   = $personenIdx->get($uid);

            // Vertragsstunden live aus allen aktiven Anstellungen im Planungs-Department berechnen.
            // Alle parallelen Anstellungen derselben Person in diesem Department werden summiert.
            // Filter: Anstellung muss im gewählten Department sein, vor/am Monatsbeginn gestartet
            // haben und darf nicht bereits vor dem Monatsbeginn geendet sein.
            $vertragsstunden = $ap->user?->employments
                ?->filter(fn($emp) =>
                    $emp->department_id === $planung->department_id
                    && $emp->start->startOfDay()->lessThanOrEqualTo($monatStart)
                    && (is_null($emp->end) || $emp->end->greaterThanOrEqualTo($monatStart))
                )
                ->sum('hours') ?: null;

            $initPersonData[$mk . '_' . $uid] = [
                'personId'       => $e?->id,
                'stunden_gesamt' => $e?->stunden_gesamt,
                'stunden_stadt'  => $e?->stunden_stadt,
                'stunden_vertrag'=> $vertragsstunden,
                'kommentar'      => $e?->kommentar,
            ];
        }

        // Zusatzstunden-Daten indexiert nach "mk_typId"
        foreach ($aktiveZusatz as $typ) {
            $zk = $mk . '_' . $typ->id;
            $z  = $m->monatZusatzstunden->firstWhere('hort_zusatzstunden_typ_id', $typ->id);
            $initZusatzData[$zk] = (float)($z?->stunden ?? 0);
        }
    }

    $alpineInit = [
        'planungId'    => $planung->id,
        'kanBearbeiten'=> $canManage,
        'monatData'    => $initMonatData,
        'personData'   => $initPersonData,
        'zusatzData'   => $initZusatzData,
        'berechnungen' => $initBerechnungen,
        'ersteMk'      => $monatsListe->first()?->monat->format('Y-m') ?? '',
        'letzteMk'     => $monatsListe->last()?->monat->format('Y-m') ?? '',
    ];

    // Abwesenheiten: Set der User-IDs mit aktiver Abwesenheit
    $abwesenheitUserIds = $abwesenheiten->keys()->toArray();

    // Min-Breite der Tabelle: 240px erste Spalte + 2 × 90px pro Monat
    $tableMinWidth = 240 + $monatsListe->count() * 180;
@endphp

{{-- Init-Daten als JSON-Script-Tag – verhindert Probleme mit Anführungszeichen im HTML-Attribut --}}
<script id="hort-planung-init-data" type="application/json">@json($alpineInit)</script>

{{-- ── Haupt-Wrapper: Single Alpine.js Component ────────────────────── --}}
<div class="px-4 py-6" x-data="hortMatrix(JSON.parse(document.getElementById('hort-planung-init-data').textContent))">

    {{-- ── Flash-Meldung ─────────────────────────────────────────────── --}}
    @if(session('Meldung'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium
                {{ session('type') === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' :
                   (session('type') === 'danger'  ? 'bg-red-50 text-red-800 border border-red-200' :
                    'bg-blue-50 text-blue-800 border border-blue-200') }}"
         role="alert">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- ── Breadcrumb ─────────────────────────────────────────────────── --}}
    <nav class="flex items-center gap-1.5 text-sm text-gray-500 mb-4">
        <a href="{{ route('hort-planung.index') }}" class="hover:text-blue-600">Hortstunden-Planung</a>
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-800 font-medium truncate max-w-xs">{{ $planung->name }}</span>
        @if($planung->aktiv)
            <span class="badge-aktiv">aktiv</span>
        @endif
        @if($planung->typ === 'rueckblick')
            <span class="badge-rueckblick">Rückblick</span>
        @else
            <span class="badge-planung">Planung</span>
        @endif
    </nav>

    {{-- ── Header & Aktions-Toolbar ────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $planung->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $planung->department?->name ?? '–' }}
                &middot; {{ $planung->start_monat->format('M Y') }} – {{ $planung->end_monat->format('M Y') }}
                &middot; {{ $allePersonen->count() }} {{ $allePersonen->count() === 1 ? 'Person' : 'Personen' }}
                &middot; {{ $monatsListe->count() }} Monate
                @if($planung->kopiertvon)
                    &middot; <span class="text-amber-600">Kopie von: {{ $planung->kopiertvon->name }}</span>
                @endif
            </p>
        </div>

        {{-- Aktions-Buttons --}}
        <div class="flex flex-wrap gap-2 shrink-0">
            {{-- Zu Heute springen --}}
            @if($monatsListe->contains(fn($m) => $m->monat->format('Y-m') === $heuteMk))
            <button onclick="document.getElementById('col-{{ $heuteMk }}').scrollIntoView({inline:'center', behavior:'smooth'})"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-blue-50 text-blue-700
                           text-xs font-medium rounded-xl border border-blue-200 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Heute
            </button>
            @endif

            {{-- Rückblick --}}
            <a href="{{ route('hort-planung.rueckblick', $planung) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-purple-50 text-purple-700
                      text-xs font-medium rounded-xl border border-purple-200 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Rückblick / Soll-Ist
            </a>

            {{-- Vergleich --}}
            @if($anderePlanungen->count() > 0)
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-gray-50 text-gray-700
                               text-xs font-medium rounded-xl border border-gray-200 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    Vergleichen ↔
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" @click.outside="open=false" x-cloak
                     class="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 min-w-52 py-1">
                    @foreach($anderePlanungen as $ap)
                    <a href="{{ route('hort-planung.vergleich', [$planung, $ap]) }}"
                       class="flex items-center px-3 py-2 text-xs text-gray-700 hover:bg-blue-50 hover:text-blue-700">
                        <span class="mr-2">{{ $ap->name }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Export --}}
            <a href="{{ route('hort-planung.export', $planung) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-emerald-50 text-emerald-700
                      text-xs font-medium rounded-xl border border-emerald-200 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Excel
            </a>

            @can('manage hort planung')
            {{-- Duplizieren --}}
            <button onclick="
                    document.getElementById('duplicateSourceName').textContent = '{{ addslashes($planung->name) }}';
                    document.getElementById('duplicateForm').action = '{{ route('hort-planung.duplicate', $planung) }}';
                    document.getElementById('duplicateModal').classList.remove('hidden');"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-amber-50 text-amber-700
                           text-xs font-medium rounded-xl border border-amber-200 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Duplizieren
            </button>

            {{-- Snapshot --}}
            <button @click="showSnapshotModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-indigo-50 text-indigo-700
                           text-xs font-medium rounded-xl border border-indigo-200 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Snapshot
            </button>

            {{-- Einstellungen --}}
            <a href="{{ route('hort-planung.edit', $planung) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-gray-100 text-gray-600
                      text-xs font-medium rounded-xl border border-gray-200 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Einstellungen
            </a>
            @endcan
        </div>
    </div>

    {{-- ── Legende & Aktionen unter der Matrix ────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-gray-500 mb-3 px-1">
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-blue-100 border border-blue-300"></span>
            <strong class="text-blue-700">SP1</strong> – Vereinsstunden (Soll gesamt)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-slate-100 border border-slate-300"></span>
            <strong class="text-slate-600">SP2</strong> – Stadtstunden (Abrechnung)
        </span>
        @if($canManage)
        <span class="text-gray-400">· Zelle klicken zum Bearbeiten</span>
        @endif
        @can('manage hort planung')
        <button @click="showPersonModal = true"
                class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700
                       text-white text-xs font-medium rounded-lg">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Person hinzufügen
        </button>
        @endcan
    </div>

    {{-- ── Ladeindikator (pro Monat) ──────────────────────────────────── --}}
    <div x-show="Object.values(saving).some(v => v)" x-cloak
         class="mb-3 px-3 py-2 bg-blue-50 text-blue-700 text-xs rounded-xl border border-blue-200 flex items-center gap-2">
        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Werte werden gespeichert…
    </div>

    {{-- ── Zeitraum- & Personen-Filter ────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mb-4 px-4 py-3 bg-white rounded-2xl
                border border-gray-200 shadow-sm text-xs">
        <span class="text-gray-500 font-semibold shrink-0">Zeitraum:</span>
        <input type="month" x-model="zeitraumVon" @change="updateVisibility()"
               min="{{ $monatsListe->first()?->monat->format('Y-m') }}"
               max="{{ $monatsListe->last()?->monat->format('Y-m') }}"
               class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-blue-400 outline-none">
        <span class="text-gray-400">–</span>
        <input type="month" x-model="zeitraumBis" @change="updateVisibility()"
               min="{{ $monatsListe->first()?->monat->format('Y-m') }}"
               max="{{ $monatsListe->last()?->monat->format('Y-m') }}"
               class="border border-gray-300 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-blue-400 outline-none">
        <button @click="nurZukunftToggle()"
                :class="zeitraumVon > '{{ $heuteMk }}' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                class="px-2.5 py-1 rounded-lg font-medium border transition-colors">Nur Zukunft</button>
        <button @click="alleAnzeigen()"
                class="px-2.5 py-1 rounded-lg font-medium bg-white text-gray-600 border border-gray-200 hover:bg-gray-50">
            Alle anzeigen</button>
        <span class="hidden sm:block h-4 w-px bg-gray-200 mx-1"></span>
        <label class="flex items-center gap-2 cursor-pointer select-none font-medium text-gray-600">
            <span class="relative inline-flex h-4 w-8 shrink-0">
                <input type="checkbox" x-model="hideInaktivePersonen" @change="updateVisibility()" class="sr-only peer">
                <span class="absolute inset-0 rounded-full bg-gray-200 peer-checked:bg-blue-600 transition-colors"></span>
                <span class="absolute top-0.5 left-0.5 w-3 h-3 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
            </span>
            Personen ohne aktiven Vertrag ausblenden
        </label>
        <span x-show="hideInaktivePersonen" x-cloak class="ml-auto text-gray-400 italic">
            (nur Personen mit Vertrag im Zeitraum)
        </span>
    </div>

    @if($monatsListe->isEmpty())
        <p class="text-sm text-gray-500">Keine Monate in dieser Planung vorhanden.</p>
        <a href="{{ route('hort-planung.edit', $planung) }}" class="mt-3 inline-block text-xs text-blue-600 hover:underline">
            Zur Einstellungsseite
        </a>
    </div>
    @else

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- ══  MATRIX-TABELLE                                              ══ --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hp-matrix-outer-wrap mb-6">
        <div id="matrix-scroll" class="hp-matrix-scroll"
             x-init="$nextTick(() => {
                 const r1 = $el.querySelector('thead tr:first-child');
                 if (r1) $el.style.setProperty('--hp-thead-r1-h', r1.offsetHeight + 'px');
             })">
        <table class="hp-matrix-table w-full text-xs border-collapse"
               style="min-width: {{ $tableMinWidth }}px">
            <colgroup>
                <col>{{-- Erste Spalte (sticky Label) --}}
                @foreach($monatsListe as $m)
                <col>{{-- SP1 --}}
                <col>{{-- SP2 --}}
                @endforeach
            </colgroup>

            {{-- ═══════════════════ THEAD ══════════════════════════════════ --}}
            <thead>
                {{-- Monatsname-Zeile --}}
                <tr class="border-b border-gray-200">
                    <th class="hp-sticky bg-gray-100 border-r border-gray-200 w-56 min-w-56 px-3 py-2.5
                               text-left text-[10px] font-semibold text-gray-600 uppercase tracking-wide">
                        Kennzahl / Person
                    </th>
                    @foreach($monatsListe as $m)
                    @php $mk = $m->monat->format('Y-m'); $istHeute = $mk === $heuteMk; @endphp
                    <th colspan="2"
                        id="col-{{ $mk }}"
                        data-mk="{{ $mk }}"
                        class="px-2 py-2.5 text-center font-semibold text-sm border-r border-gray-200
                               {{ $istHeute ? 'hp-col-heute-th' : 'bg-gray-50 text-gray-700' }}"
                        :class="saving['{{ $mk }}'] ? 'opacity-60' : ''">
                        <span>{{ $m->monat->locale('de')->isoFormat('MMMM') }}</span>
                        <span class="block text-[10px] font-normal {{ $istHeute ? 'text-blue-600' : 'text-gray-400' }}">
                            {{ $m->monat->format('Y') }}
                            @if($istHeute)<span class="font-semibold">← heute</span>@endif
                        </span>
                        @if($m->notiz)
                            <span class="block text-[9px] text-amber-600 italic truncate max-w-24">{{ $m->notiz }}</span>
                        @endif
                    </th>
                    @endforeach
                </tr>
                {{-- SP1 / SP2 Subheader --}}
                <tr class="border-b-2 border-gray-300 bg-gray-50">
                    <th class="hp-sticky bg-gray-50 border-r border-gray-200 px-3 py-1.5"></th>
                    @foreach($monatsListe as $m)
                    @php $mk = $m->monat->format('Y-m'); @endphp
                    <th data-mk="{{ $mk }}" class="px-2 py-1.5 text-center font-semibold text-[10px] text-blue-700 bg-blue-50/50 w-20 min-w-20
                               {{ $mk === $heuteMk ? 'bg-blue-100/60' : '' }}">SP1</th>
                    <th data-mk="{{ $mk }}" class="px-2 py-1.5 text-center font-semibold text-[10px] text-slate-600 bg-slate-50/50 w-20 min-w-20
                               border-r border-gray-200 {{ $mk === $heuteMk ? 'bg-slate-100/60' : '' }}">SP2</th>
                    @endforeach
                </tr>
            </thead>

            {{-- ═══════════════════ TBODY ══════════════════════════════════ --}}

            {{-- ── § PARAMETER ────────────────────────────────────────────── --}}
            <tbody>
            <tr>
                <td data-header-colspan
                    colspan="{{ 1 + $monatsListe->count() * 2 }}"
                    class="hp-sticky bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Parameter
                </td>
            </tr>

            {{-- Kinderanzahl --}}
            <tr class="hp-row-parameter border-b border-gray-100">
                <td class="hp-sticky bg-sky-50/70 border-r border-gray-200 px-3 py-2 font-medium text-gray-700 text-[11px]">
                    <div class="flex items-center justify-between gap-1">
                        <span>Kinderanzahl
                            <span class="block text-[9px] text-gray-400 font-normal">Anzahl betreuter Kinder</span>
                        </span>
                        @if($canManage)
                        <button title="Wert auf alle folgenden Monate übertragen"
                                class="text-gray-300 hover:text-blue-500 shrink-0 text-[10px]"
                                x-show="false"
                                x-data
                                @click.stop>→</button>
                        @endif
                    </div>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); $editKey = 'km_' . $mk; @endphp
                <td colspan="2"
                    data-mk="{{ $mk }}"
                    class="px-2 py-2 text-center hp-row-parameter border-r border-gray-200
                           {{ $canManage ? 'hp-editable' : '' }}"
                    @if($canManage) @click="startEdit('{{ $editKey }}')" @endif>
                    <span x-show="!editing['{{ $editKey }}']"
                          x-text="monatData['{{ $mk }}']?.kinderanzahl ?? '–'">
                        {{ $m->kinderanzahl }}
                    </span>
                    @if($canManage)
                    <div x-show="editing['{{ $editKey }}']" x-cloak class="flex items-center gap-1 justify-center">
                        <input data-edit="{{ $editKey }}"
                               type="number" step="1" min="0"
                               x-model.number="monatData['{{ $mk }}'].kinderanzahl"
                               @blur="saveMonat('{{ $mk }}')"
                               @keydown.enter.prevent="saveMonat('{{ $mk }}')"
                               @keydown.escape="cancelEdit('{{ $editKey }}')"
                               @click.stop>
                        <button type="button"
                                title="Wert auf alle folgenden Monate übertragen"
                                class="text-blue-400 hover:text-blue-600 text-[10px] shrink-0"
                                @mousedown.prevent
                                @click.stop="propagiereMonatParam('{{ $mk }}', 'kinderanzahl')">→</button>
                    </div>
                    @endif
                </td>
                @endforeach
            </tr>

            {{-- Vollzeitstunden --}}
            <tr class="hp-row-parameter border-b border-gray-100">
                <td class="hp-sticky bg-sky-50/70 border-r border-gray-200 px-3 py-2 font-medium text-gray-700 text-[11px]">
                    Vollzeitstunden
                    <span class="block text-[9px] text-gray-400 font-normal">Std./Woche Vollzeitstelle</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); $editKey = 'vz_' . $mk; @endphp
                <td colspan="2"
                    data-mk="{{ $mk }}"
                    class="px-2 py-2 text-center hp-row-parameter border-r border-gray-200
                           {{ $canManage ? 'hp-editable' : '' }}"
                    @if($canManage) @click="startEdit('{{ $editKey }}')" @endif>
                    <span x-show="!editing['{{ $editKey }}']"
                          x-text="fmtNum(monatData['{{ $mk }}']?.vollzeitstunden, 0)">
                        {{ number_format($m->vollzeitstunden, 0, ',', '.') }}
                    </span>
                    @if($canManage)
                    <div x-show="editing['{{ $editKey }}']" x-cloak class="flex items-center gap-1 justify-center">
                        <input data-edit="{{ $editKey }}"
                               type="number" step="0.5" min="1"
                               x-model.number="monatData['{{ $mk }}'].vollzeitstunden"
                               @blur="saveMonat('{{ $mk }}')"
                               @keydown.enter.prevent="saveMonat('{{ $mk }}')"
                               @keydown.escape="cancelEdit('{{ $editKey }}')"
                               @click.stop>
                        <button type="button"
                                title="Wert auf alle folgenden Monate übertragen"
                                class="text-blue-400 hover:text-blue-600 text-[10px] shrink-0"
                                @mousedown.prevent
                                @click.stop="propagiereMonatParam('{{ $mk }}', 'vollzeitstunden')">→</button>
                    </div>
                    @endif
                </td>
                @endforeach
            </tr>

            {{-- Zusatzstunden je aktivem Typ --}}
            @foreach($aktiveZusatz as $typ)
            <tr class="hp-row-parameter border-b border-gray-100">
                <td class="hp-sticky bg-sky-50/70 border-r border-gray-200 px-3 py-2 font-medium text-gray-700 text-[11px]">
                    {{ $typ->bezeichnung }}
                    <span class="block text-[9px] text-gray-400 font-normal font-mono">{{ $typ->kuerzel }}</span>
                </td>
                @foreach($monatsListe as $m)
                @php
                    $mk      = $m->monat->format('Y-m');
                    $editKey = 'zs_' . $mk . '_' . $typ->id;
                    $zKey    = $mk . '_' . $typ->id;
                @endphp
                <td colspan="2"
                    data-mk="{{ $mk }}"
                    class="px-2 py-2 text-center hp-row-parameter border-r border-gray-200
                           {{ $canManage ? 'hp-editable' : '' }}"
                    @if($canManage) @click="startEdit('{{ $editKey }}')" @endif>
                    <span x-show="!editing['{{ $editKey }}']"
                          x-text="fmtNum(zusatzData['{{ $zKey }}'] ?? 0, 1)">
                        {{ number_format($initZusatzData[$zKey] ?? 0, 1, ',', '.') }}
                    </span>
                    @if($canManage)
                    <div x-cloak x-show="editing['{{ $editKey }}']" class="flex items-center gap-0.5 justify-center">
                        <input data-edit="{{ $editKey }}"
                               type="number" step="0.5" min="0"
                               x-model.number="zusatzData['{{ $zKey }}']"
                               @blur="saveZusatz('{{ $mk }}', {{ $typ->id }}, monatData['{{ $mk }}'].id)"
                               @keydown.enter.prevent="saveZusatz('{{ $mk }}', {{ $typ->id }}, monatData['{{ $mk }}'].id)"
                               @keydown.escape="cancelEdit('{{ $editKey }}')"
                               @click.stop>
                        <button type="button" title="Zusatzstunden auf alle Folgemonate übertragen"
                                class="text-blue-400 hover:text-blue-600 text-[11px] shrink-0 leading-none px-0.5"
                                @mousedown.prevent
                                @click.stop="propagiereZusatz('{{ $mk }}', {{ $typ->id }})">→</button>
                    </div>
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
            </tbody>{{-- Ende Parameter-tbody --}}

            {{-- ── § PERSONEN ────────────────────────────────────────────── --}}
            <tbody>
            <tr>
                <td data-header-colspan
                    colspan="{{ 1 + $monatsListe->count() * 2 }}"
                    class="hp-sticky bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Personal
                </td>
            </tr>
            </tbody>

            @forelse($allePersonen as $pi => $ap)
            @php
                $uid      = $ap->user_id;
                $userName = $ap->user?->name ?? 'Unbekannt (#' . $uid . ')';
                $rowCss   = $pi % 2 === 0 ? 'hp-row-person' : 'hp-row-person-alt';
                $stickyBg = $pi % 2 === 0 ? 'bg-white' : 'bg-slate-50';
                $hatAbwesenheit = in_array($uid, $abwesenheitUserIds);
            @endphp

            {{-- Pro Person: eigener tbody für x-show (Aktivitätsfilter) --}}
            <tbody x-show="!hideInaktivePersonen || hatVertragInSichtbarenMonaten({{ $uid }})">

            {{-- Person-Hauptzeile: SP1 (editable) + SP2 (editable) --}}
            <tr class="{{ $rowCss }} border-b border-gray-100">
                {{-- Personenname (sticky) --}}
                <td class="hp-sticky {{ $stickyBg }} border-r border-gray-200 px-3 py-2 font-medium text-gray-800 text-[11px]">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        {{ $userName }}
                        @if($hatAbwesenheit)
                        @php $abs = $abwesenheiten->get($uid); @endphp
                        <span class="hp-warning-badge" title="{{ $abs?->first()?->reason ?? 'Langzeitabwesenheit' }}">
                            ⚠ {{ $abs?->first()?->reason ? \Illuminate\Support\Str::limit($abs->first()->reason, 15) : 'EZ/LK' }}
                        </span>
                        @endif
                        @if($canManage)
                        <button @click.stop="openBulkEdit({{ $uid }}, '{{ addslashes($userName) }}')"
                                class="ml-auto text-gray-300 hover:text-blue-500 flex-shrink-0"
                                title="Stunden ab Monat X setzen (Folgemonate)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        @endif
                    </div>
                </td>

                @foreach($monatsListe as $m)
                @php
                    $mk       = $m->monat->format('Y-m');
                    $pKey     = $mk . '_' . $uid;
                    $editKeyG = 'pg_' . $mk . '_' . $uid;
                    $editKeyS = 'ps_' . $mk . '_' . $uid;
                    $istHeuteMk = $mk === $heuteMk;
                @endphp

                {{-- SP1: stunden_gesamt --}}
                <td data-mk="{{ $mk }}"
                    class="px-1.5 py-2 text-center {{ $rowCss }} {{ $canManage ? 'hp-editable' : '' }}
                           {{ $istHeuteMk ? 'bg-blue-50/30' : '' }}"
                    @if($canManage) @click="startEdit('{{ $editKeyG }}')" @endif>
                    <span x-show="!editing['{{ $editKeyG }}']"
                          x-text="personData?.['{{ $pKey }}']?.stunden_gesamt !== undefined && personData['{{ $pKey }}'].stunden_gesamt !== null
                                  ? fmtNum(personData['{{ $pKey }}'].stunden_gesamt, 1) : '–'">
                        {{ isset($initPersonData[$pKey]['stunden_gesamt']) ? number_format($initPersonData[$pKey]['stunden_gesamt'], 1, ',', '.') : '–' }}
                    </span>
                    @if($canManage)
                    <div x-cloak x-show="editing['{{ $editKeyG }}']" class="flex items-center gap-0.5 justify-center">
                        <input data-edit="{{ $editKeyG }}"
                               type="number" step="0.5" min="0"
                               x-model.number="personData['{{ $pKey }}'].stunden_gesamt"
                               @blur="savePerson('{{ $mk }}', {{ $uid }})"
                               @keydown.enter.prevent="savePerson('{{ $mk }}', {{ $uid }})"
                               @keydown.escape="cancelEdit('{{ $editKeyG }}')"
                               @click.stop>
                        <button type="button" title="SP1-Wert auf alle Folgemonate übertragen"
                                class="text-blue-400 hover:text-blue-600 text-[11px] shrink-0 leading-none px-0.5"
                                @mousedown.prevent
                                @click.stop="propagierePerson('{{ $mk }}', {{ $uid }}, 'stunden_gesamt')">→</button>
                    </div>
                    @endif
                </td>

                {{-- SP2: stunden_stadt --}}
                <td data-mk="{{ $mk }}"
                    class="px-1.5 py-2 text-center {{ $rowCss }} border-r border-gray-200
                           {{ $canManage ? 'hp-editable' : '' }}
                           {{ $istHeuteMk ? 'bg-slate-50/40' : '' }}"
                    @if($canManage) @click="startEdit('{{ $editKeyS }}')" @endif>
                    <span x-show="!editing['{{ $editKeyS }}']"
                          x-text="personData?.['{{ $pKey }}']?.stunden_stadt !== undefined && personData['{{ $pKey }}'].stunden_stadt !== null
                                  ? fmtNum(personData['{{ $pKey }}'].stunden_stadt, 1) : '–'">
                        {{ isset($initPersonData[$pKey]['stunden_stadt']) ? number_format($initPersonData[$pKey]['stunden_stadt'], 1, ',', '.') : '–' }}
                    </span>
                    @if($canManage)
                    <div x-cloak x-show="editing['{{ $editKeyS }}']" class="flex items-center gap-0.5 justify-center">
                        <input data-edit="{{ $editKeyS }}"
                               type="number" step="0.5" min="0"
                               x-model.number="personData['{{ $pKey }}'].stunden_stadt"
                               @blur="savePerson('{{ $mk }}', {{ $uid }})"
                               @keydown.enter.prevent="savePerson('{{ $mk }}', {{ $uid }})"
                               @keydown.escape="cancelEdit('{{ $editKeyS }}')"
                               @click.stop>
                        <button type="button" title="SP2-Wert auf alle Folgemonate übertragen"
                                class="text-slate-400 hover:text-slate-600 text-[11px] shrink-0 leading-none px-0.5"
                                @mousedown.prevent
                                @click.stop="propagierePerson('{{ $mk }}', {{ $uid }}, 'stunden_stadt')">→</button>
                    </div>
                    @endif
                </td>
                @endforeach
            </tr>

            {{-- Vertrag-Unterzeile (readonly) --}}
            <tr class="hp-row-vertrag border-b border-gray-100/60">
                <td class="hp-sticky bg-slate-100/40 border-r border-gray-200 px-3 py-1 text-[9px] text-gray-400 italic pl-5">
                    └ Vertrag
                </td>
                @foreach($monatsListe as $m)
                @php $mk2 = $m->monat->format('Y-m'); $pKey2 = $mk2 . '_' . $uid; @endphp
                {{-- Erste Zelle (colspan 1): Vertrag-Stunden mit Hervorhebung bei Abweichung --}}
                <td data-mk="{{ $mk2 }}"
                    class="px-1.5 py-1 text-center text-[10px]"
                    :class="(() => {
                        const p = personData?.['{{ $pKey2 }}'];
                        if (!p) return '';
                        const vt = p.stunden_vertrag, sp = p.stunden_gesamt;
                        if (vt === null || vt === undefined || sp === null || sp === undefined) return 'text-gray-400';
                        return Math.abs(parseFloat(vt) - parseFloat(sp)) > 0.001
                            ? 'bg-amber-100 text-amber-800 font-semibold'
                            : 'text-gray-400';
                    })()"
                    :title="(() => {
                        const p = personData?.['{{ $pKey2 }}'];
                        if (!p) return '';
                        const vt = p.stunden_vertrag, sp = p.stunden_gesamt;
                        if (vt === null || vt === undefined || sp === null || sp === undefined) return '';
                        return Math.abs(parseFloat(vt) - parseFloat(sp)) > 0.001
                            ? 'Vertrag (' + vt + ' h) weicht von SP1 (' + sp + ' h) ab – Vertrag anpassen!'
                            : '';
                    })()">
                    <span x-text="personData?.['{{ $pKey2 }}']?.stunden_vertrag !== null && personData?.['{{ $pKey2 }}']?.stunden_vertrag !== undefined
                                  ? fmtNum(personData['{{ $pKey2 }}'].stunden_vertrag, 1) : ''">
                        {{ isset($initPersonData[$pKey2]['stunden_vertrag']) ? number_format($initPersonData[$pKey2]['stunden_vertrag'], 1, ',', '.') : '' }}
                    </span>
                    {{-- Warnsymbol bei Abweichung --}}
                    <span x-show="(() => {
                        const p = personData?.['{{ $pKey2 }}'];
                        if (!p) return false;
                        const vt = p.stunden_vertrag, sp = p.stunden_gesamt;
                        if (vt === null || vt === undefined || sp === null || sp === undefined) return false;
                        return Math.abs(parseFloat(vt) - parseFloat(sp)) > 0.001;
                    })()" class="ml-0.5 text-amber-600" title="Vertragsanpassung nötig">⚠</span>
                </td>
                <td data-mk="{{ $mk2 }}" class="border-r border-gray-200"></td>
                @endforeach
            </tr>

            @if($canManage)
            {{-- Entfernen-Zeile (nur für manage) --}}
            <tr class="border-b border-gray-200">
                <td class="hp-sticky bg-white border-r border-gray-200 px-3 py-1">
                    <form method="POST" action="{{ route('hort-planung.removePerson', [$planung, $uid]) }}"
                          onsubmit="return confirm('{{ $userName }} aus allen Monaten entfernen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[9px] text-red-400 hover:text-red-600 hover:underline">
                            Person entfernen
                        </button>
                    </form>
                </td>
                <td colspan="{{ $monatsListe->count() * 2 }}"></td>
            </tr>
            @endif

            </tbody>{{-- Ende Person-tbody --}}

            @empty
            <tbody>
            <tr>
                <td class="hp-sticky bg-white border-r border-gray-200 px-3 py-6 text-gray-400 italic text-center"
                    colspan="{{ 1 + $monatsListe->count() * 2 }}">
                    Noch keine Personen vorhanden.
                    @if($canManage)
                        <button @click="showPersonModal = true" class="ml-2 text-blue-600 hover:underline not-italic">
                            Person hinzufügen
                        </button>
                    @endif
                </td>
            </tr>
            </tbody>
            @endforelse

            {{-- ── § SUMMEN ──────────────────────────────────────────────── --}}
            <tbody>
            <tr>
                <td data-header-colspan
                    colspan="{{ 1 + $monatsListe->count() * 2 }}"
                    class="hp-sticky bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Summen
                </td>
            </tr>

            {{-- Summe Stunden SP1 / SP2 --}}
            <tr class="hp-row-summe border-b border-gray-200">
                <td class="hp-sticky bg-blue-50/70 border-r border-gray-200 px-3 py-2 text-gray-700 font-semibold text-[11px]">
                    Summe Stunden
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-blue-700 font-semibold bg-blue-50/40">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.summe_sp1, 2)">
                        {{ isset($initBerechnungen[$mk]['summe_sp1']) ? number_format($initBerechnungen[$mk]['summe_sp1'], 2, ',', '.') : '–' }}
                    </span>
                </td>
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-slate-700 font-semibold bg-slate-50/40 border-r border-gray-200">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.summe_sp2, 2)">
                        {{ isset($initBerechnungen[$mk]['summe_sp2']) ? number_format($initBerechnungen[$mk]['summe_sp2'], 2, ',', '.') : '–' }}
                    </span>
                </td>
                @endforeach
            </tr>

            {{-- Σ Verträge – Summe aller Vertragsstunden pro Monat --}}
            <tr class="hp-row-summe border-b border-gray-200">
                <td class="hp-sticky bg-blue-50/70 border-r border-gray-200 px-3 py-2 text-gray-700 font-semibold text-[11px]">
                    Σ Verträge
                    <span class="block text-[9px] text-gray-400 font-normal">Vertragliche Gesamtstunden</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                {{-- Hervorhebung wenn Vertragssumme von SP1 abweicht --}}
                <td data-mk="{{ $mk }}"
                    class="px-2 py-2 text-center font-semibold"
                    :class="(() => {
                        const vt = berechnungen['{{ $mk }}']?.summe_vertrag;
                        const sp = berechnungen['{{ $mk }}']?.summe_sp1;
                        if (vt === undefined || vt === null || sp === undefined || sp === null) return 'text-gray-500';
                        return Math.abs(parseFloat(vt) - parseFloat(sp)) > 0.01
                            ? 'bg-amber-100 text-amber-800'
                            : 'text-gray-600 bg-slate-50/40';
                    })()"
                    :title="(() => {
                        const vt = berechnungen['{{ $mk }}']?.summe_vertrag;
                        const sp = berechnungen['{{ $mk }}']?.summe_sp1;
                        if (vt === undefined || vt === null || sp === undefined || sp === null) return '';
                        const diff = Math.round((parseFloat(sp) - parseFloat(vt)) * 100) / 100;
                        return Math.abs(diff) > 0.01
                            ? 'SP1 weicht vom Vertrag ab: Δ ' + (diff > 0 ? '+' : '') + diff + ' h'
                            : 'SP1 entspricht dem Vertrag';
                    })()">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.summe_vertrag, 2)">
                        {{ isset($initBerechnungen[$mk]['summe_vertrag']) ? number_format($initBerechnungen[$mk]['summe_vertrag'], 2, ',', '.') : '–' }}
                    </span>
                    <span x-show="(() => {
                        const vt = berechnungen['{{ $mk }}']?.summe_vertrag;
                        const sp = berechnungen['{{ $mk }}']?.summe_sp1;
                        if (vt === undefined || vt === null || sp === undefined || sp === null) return false;
                        return Math.abs(parseFloat(vt) - parseFloat(sp)) > 0.01;
                    })()" class="ml-0.5 text-amber-600 text-[10px]">⚠</span>
                </td>
                <td data-mk="{{ $mk }}" class="border-r border-gray-200"></td>
                @endforeach
            </tr>

            {{-- Summe VZÄ SP1 / SP2 --}}
            <tr class="hp-row-summe border-b border-gray-300">
                <td class="hp-sticky bg-blue-50/70 border-r border-gray-200 px-3 py-2 text-gray-700 font-semibold text-[11px]">
                    Summe VZÄ
                    <span class="block text-[9px] text-gray-400 font-normal">Vollzeitäquivalente</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-blue-800 font-bold bg-blue-50/60">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.summe_vz_sp1, 3)">
                        {{ isset($initBerechnungen[$mk]['summe_vz_sp1']) ? number_format($initBerechnungen[$mk]['summe_vz_sp1'], 3, ',', '.') : '–' }}
                    </span>
                </td>
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-slate-700 font-bold bg-slate-50/60 border-r border-gray-200">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.summe_vz_sp2, 3)">
                        {{ isset($initBerechnungen[$mk]['summe_vz_sp2']) ? number_format($initBerechnungen[$mk]['summe_vz_sp2'], 3, ',', '.') : '–' }}
                    </span>
                </td>
                @endforeach
            </tr>
            </tbody>{{-- Ende Summen-tbody --}}

            {{-- ── § GESETZLICHE FAKTOREN ──────────────────────────────────── --}}
            <tbody>
            <tr>
                <td data-header-colspan
                    colspan="{{ 1 + $monatsListe->count() * 2 }}"
                    class="hp-sticky bg-amber-100/50 border-y border-amber-200 px-3 py-1 text-[10px] font-bold
                           text-amber-700 uppercase tracking-widest">
                    ⚖ Gesetzliche Faktoren (§12 SächsKitaG)
                </td>
            </tr>

            @foreach($aktiveFaktoren as $faktor)
            <tr class="hp-row-gesetzl border-b border-amber-100/60">
                <td class="hp-sticky bg-amber-50/60 border-r border-gray-200 px-3 py-2 text-gray-700 text-[11px]">
                    <span class="font-medium">{{ $faktor->bezeichnung }}</span>
                    <span class="block text-[9px] text-gray-400">
                        @switch($faktor->berechnungs_typ)
                            @case('divisor')          ÷ Grundschlüssel @break
                            @case('faktor_auf_bs')    × BS-Aufschlag @break
                            @case('faktor_auf_summe') × Summen-Aufschlag @break
                            @default {{ $faktor->berechnungs_typ }}
                        @endswitch
                    </span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-amber-800 font-semibold bg-amber-50/30">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.faktoren?.['{{ $faktor->kuerzel }}']?.vz, 3)">
                        {{ isset($initBerechnungen[$mk]['faktoren'][$faktor->kuerzel]['vz']) ? number_format($initBerechnungen[$mk]['faktoren'][$faktor->kuerzel]['vz'], 3, ',', '.') : '–' }}
                    </span>
                </td>
                <td data-mk="{{ $mk }}" class="px-2 py-2 bg-amber-50/20 border-r border-gray-200 text-center text-[9px] text-amber-400">
                    @if($faktor->berechnungs_typ === 'divisor')
                        <span x-text="fmtNum(berechnungen['{{ $mk }}']?.faktoren?.['{{ $faktor->kuerzel }}']?.wert, 4)"></span>
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
            </tbody>{{-- Ende Faktoren-tbody --}}

            {{-- ── § ERGEBNISSE & BUDGET ──────────────────────────────────── --}}
            <tbody>
            <tr>
                <td data-header-colspan
                    colspan="{{ 1 + $monatsListe->count() * 2 }}"
                    class="hp-sticky bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Ergebnis
                </td>
            </tr>

            {{-- Summe gesetz. VZÄ --}}
            <tr class="hp-row-ergebnis border-b border-gray-100">
                <td class="hp-sticky bg-gray-50/80 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                    Summe gesetz. VZÄ
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-amber-700 font-bold">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.summe_gesetz_vz, 3)">
                        {{ isset($initBerechnungen[$mk]['summe_gesetz_vz']) ? number_format($initBerechnungen[$mk]['summe_gesetz_vz'], 3, ',', '.') : '–' }}
                    </span>
                </td>
                <td data-mk="{{ $mk }}" class="border-r border-gray-200"></td>
                @endforeach
            </tr>

            {{-- Summe Stunden gesetzl. --}}
            <tr class="hp-row-ergebnis border-b border-gray-100">
                <td class="hp-sticky bg-gray-50/80 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                    Stunden gesetzl. Minimum
                    <span class="block text-[9px] text-gray-400">VZÄ × Vollzeitstunden</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-amber-700">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.summe_stunden_gesetzl, 2)">
                        {{ isset($initBerechnungen[$mk]['summe_stunden_gesetzl']) ? number_format($initBerechnungen[$mk]['summe_stunden_gesetzl'], 2, ',', '.') : '–' }}
                    </span>
                </td>
                <td data-mk="{{ $mk }}" class="border-r border-gray-200"></td>
                @endforeach
            </tr>

            {{-- Budget gesamt --}}
            <tr class="hp-row-ergebnis border-b border-gray-100">
                <td class="hp-sticky bg-gray-50/80 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                    Budget gesamt
                    <span class="block text-[9px] text-gray-400">Gesetzl. + Zusatzstunden</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-gray-700">
                    <span x-text="fmtNum(berechnungen['{{ $mk }}']?.budget_gesamt, 2)">
                        {{ isset($initBerechnungen[$mk]['budget_gesamt']) ? number_format($initBerechnungen[$mk]['budget_gesamt'], 2, ',', '.') : '–' }}
                    </span>
                </td>
                <td data-mk="{{ $mk }}" class="border-r border-gray-200"></td>
                @endforeach
            </tr>

            {{-- Budget-Rest (SP1) --}}
            <tr class="border-b-2 border-gray-300">
                <td class="hp-sticky bg-gray-100 border-r border-gray-200 px-3 py-2 font-bold text-gray-800 text-[11px]">
                    📊 Budget-Rest (SP1)
                    <span class="block text-[9px] text-gray-500 font-normal">Budget − Σ SP1 Personen</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2.5 text-center font-bold text-sm"
                    :class="{
                        'budget-positiv': (berechnungen['{{ $mk }}']?.budget_rest_sp1 ?? 0) >= 0,
                        'budget-negativ': (berechnungen['{{ $mk }}']?.budget_rest_sp1 ?? 0) < 0
                    }">
                    <span x-text="fmtSign(berechnungen['{{ $mk }}']?.budget_rest_sp1, 2)">
                        @php $br = $initBerechnungen[$mk]['budget_rest_sp1'] ?? null; @endphp
                        @if($br !== null)
                            <span class="{{ $br >= 0 ? 'budget-positiv' : 'budget-negativ' }}">
                                {{ ($br >= 0 ? '+' : '') . number_format($br, 2, ',', '.') }}
                            </span>
                        @else –
                        @endif
                    </span>
                </td>
                <td data-mk="{{ $mk }}" class="border-r border-gray-200"></td>
                @endforeach
            </tr>

            {{-- Diff. VZÄ Stadt (SP2) --}}
            <tr class="hp-row-ergebnis border-b border-gray-100">
                <td class="hp-sticky bg-gray-50/80 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                    Diff. VZÄ Stadt (SP2)
                    <span class="block text-[9px] text-gray-400">VZÄ SP2 − gesetzl. VZÄ</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-gray-400"></td>
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center font-semibold border-r border-gray-200"
                    :class="{
                        'diff-positiv': (berechnungen['{{ $mk }}']?.differenz_vz_sp2 ?? 0) >= 0,
                        'diff-negativ': (berechnungen['{{ $mk }}']?.differenz_vz_sp2 ?? 0) < 0
                    }">
                    <span x-text="fmtSign(berechnungen['{{ $mk }}']?.differenz_vz_sp2, 3)">
                        @php $dv = $initBerechnungen[$mk]['differenz_vz_sp2'] ?? null; @endphp
                        {{ $dv !== null ? (($dv >= 0 ? '+' : '') . number_format($dv, 3, ',', '.')) : '–' }}
                    </span>
                </td>
                @endforeach
            </tr>

            {{-- Diff. Stunden Stadt (SP2) --}}
            <tr class="hp-row-ergebnis border-b border-gray-200">
                <td class="hp-sticky bg-gray-50/80 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                    Diff. Stunden Stadt (SP2)
                    <span class="block text-[9px] text-gray-400">SP2 − gesetzl. Stunden</span>
                </td>
                @foreach($monatsListe as $m)
                @php $mk = $m->monat->format('Y-m'); @endphp
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center text-gray-400"></td>
                <td data-mk="{{ $mk }}" class="px-2 py-2 text-center font-semibold border-r border-gray-200"
                    :class="{
                        'diff-positiv': (berechnungen['{{ $mk }}']?.differenz_stadt ?? 0) >= 0,
                        'diff-negativ': (berechnungen['{{ $mk }}']?.differenz_stadt ?? 0) < 0
                    }">
                    <span x-text="fmtSign(berechnungen['{{ $mk }}']?.differenz_stadt, 2)">
                        @php $ds = $initBerechnungen[$mk]['differenz_stadt'] ?? null; @endphp
                        {{ $ds !== null ? (($ds >= 0 ? '+' : '') . number_format($ds, 2, ',', '.')) : '–' }}
                    </span>
                </td>
                @endforeach
            </tr>

            </tbody>{{-- Ende Ergebnis-tbody --}}
        </table>
        </div>

        {{-- Farb-Legende --}}
        <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50/80 flex flex-wrap gap-x-5 gap-y-1 text-[10px] text-gray-500">
            <span><span class="budget-positiv">+Wert</span> = Budget-Rest positiv (noch verteilbare Stunden)</span>
            <span><span class="budget-negativ">−Wert</span> = Überplanung (mehr Stunden als Budget)</span>
            <span><span class="diff-positiv">+Wert</span> = Diff. Stadt positiv (Stadt zahlt mehr als Minimum)</span>
            <span><span class="diff-negativ">−Wert</span> = Diff. Stadt negativ (unter gesetzlichem Minimum)</span>
            <span><span class="inline-block px-1 rounded bg-amber-100 text-amber-800 font-medium">⚠ Vertrag</span> = SP1 weicht von Vertragsstunden ab – Vertragsanpassung prüfen</span>
            @if($canManage)<span class="text-blue-500">· Zellen klicken zum Bearbeiten</span>@endif
        </div>
    </div>

    {{-- ── Budget-Zeiträume ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- SP1 Vereinsjahr (August–Juli) --}}
        <div class="bg-white rounded-2xl border border-blue-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-blue-100 bg-blue-50 flex items-center gap-2">
                <span class="text-base">📋</span>
                <div>
                    <h3 class="text-sm font-semibold text-blue-800">Budget SP1 – Vereinsjahr (Aug–Jul)</h3>
                    <p class="text-xs text-blue-500 mt-0.5">Haushaltsjahr des Vereins: August bis Juli</p>
                </div>
            </div>
                <table class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-500">
                        <th class="px-4 py-2 text-left font-semibold">Vereinsjahr</th>
                        <th class="px-4 py-2 text-right font-semibold">Monate</th>
                        <th class="px-4 py-2 text-right font-semibold text-blue-700">Σ SP1 (h)</th>
                        <th class="px-4 py-2 text-right font-semibold text-gray-600">Σ Vertrag (h)</th>
                        <th class="px-4 py-2 text-right font-semibold text-amber-600">Δ SP1–Vertrag</th>
                        <th class="px-4 py-2 text-right font-semibold">Budget (h)</th>
                        <th class="px-4 py-2 text-right font-semibold">Rest (h)</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="p in sp1Perioden" :key="p.label">
                        <tr class="border-b border-gray-100 hover:bg-blue-50/30">
                            <td class="px-4 py-2.5 font-semibold text-gray-700" x-text="'VJ ' + p.label"></td>
                            <td class="px-4 py-2.5 text-right text-gray-400 font-mono text-[10px]"
                                x-text="p.anzahl + ' Mon.'"></td>
                            <td class="px-4 py-2.5 text-right text-blue-700 font-semibold"
                                x-text="fmtNum(p.sumSp1, 2)"></td>
                            <td class="px-4 py-2.5 text-right text-gray-600"
                                x-text="fmtNum(p.sumVertrag, 2)"></td>
                            <td class="px-4 py-2.5 text-right font-semibold"
                                :class="Math.abs(p.abwVertrag) < 0.01 ? 'text-gray-400' : (p.abwVertrag > 0 ? 'text-orange-600 bg-amber-50' : 'text-blue-600 bg-blue-50')"
                                :title="Math.abs(p.abwVertrag) > 0.01 ? (p.abwVertrag > 0 ? 'SP1 über Vertrag – Verträge erhöhen' : 'SP1 unter Vertrag – Verträge ggf. reduzieren') : 'SP1 entspricht Vertrag'"
                                x-text="Math.abs(p.abwVertrag) < 0.01 ? '–' : fmtSign(p.abwVertrag, 2)"></td>
                            <td class="px-4 py-2.5 text-right text-gray-600"
                                x-text="fmtNum(p.budget, 2)"></td>
                            <td class="px-4 py-2.5 text-right font-bold"
                                :class="p.rest >= 0 ? 'budget-positiv' : 'budget-negativ'"
                                x-text="fmtSign(p.rest, 2)"></td>
                        </tr>
                    </template>
                    <template x-if="sp1Perioden.length === 0">
                        <tr><td colspan="7" class="px-4 py-4 text-center text-gray-400 italic">Keine Monate im Zeitraum</td></tr>
                    </template>
                </tbody>
            </table>
            <div class="px-5 py-2 bg-gray-50 border-t border-gray-100">
                <p class="text-[10px] text-gray-400">
                    Budget = Σ gesetzl. Stunden + Zusatzstunden. Rest = Budget − Σ SP1 (Verein).
                    <span class="text-amber-600">Δ SP1–Vertrag: positiv = Verträge müssen erhöht werden, negativ = Übervertrag.</span>
                </p>
            </div>
        </div>

        {{-- SP2 Stadtjahr (Januar–Dezember) --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50 flex items-center gap-2">
                <span class="text-base">🏛</span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-700">Budget SP2 – Stadtjahr (Jan–Dez)</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Abrechnungsjahr der Stadt: Januar bis Dezember</p>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    {{-- Gruppenzeile: verdeutlicht welche Spalten zusammengehören --}}
                    <tr class="bg-gray-50 border-b border-gray-100 text-[10px] uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-1" colspan="2"></th>
                        <th class="px-3 py-1 text-center border-l border-gray-200 text-slate-500 font-semibold" colspan="3">
                            Stunden (h) – Jahressumme
                        </th>
                        <th class="px-3 py-1 text-center border-l border-gray-200 text-slate-500 font-semibold" colspan="3">
                            Vollzeitäquivalente – Ø je Monat
                        </th>
                    </tr>
                    {{-- Spaltenköpfe --}}
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-500">
                        <th class="px-4 py-2 text-left font-semibold">Stadtjahr</th>
                        <th class="px-4 py-2 text-right font-semibold text-gray-400">Mon.</th>
                        {{-- Stunden-Gruppe --}}
                        <th class="px-3 py-2 text-right font-semibold text-slate-700 border-l border-gray-200"
                            title="Jahressumme der mit der Stadt abgerechneten SP2-Stunden">
                            Σ SP2
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-amber-700"
                            title="Jahressumme des gesetzlichen Mindest-Stundenbedarfs (§12 SächsKitaG)">
                            Σ Minimum
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-600"
                            title="SP2 − Minimum: positiv = Abrechnung über Minimum; negativ = Unterdeckung">
                            Δ
                        </th>
                        {{-- VZÄ-Gruppe --}}
                        <th class="px-3 py-2 text-right font-semibold text-slate-700 border-l border-gray-200"
                            title="Durchschnittliche VZÄ SP2 je Monat im Stadtjahr">
                            Ø VZÄ SP2
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-amber-700"
                            title="Durchschnittliches gesetzl. Mindest-VZÄ je Monat (§12 SächsKitaG)">
                            Ø VZÄ Min.
                        </th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-600"
                            title="Ø VZÄ SP2 − Ø VZÄ Minimum: positiv = über Mindest-VZÄ; negativ = unter Mindest-VZÄ">
                            Δ VZÄ
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="p in sp2Perioden" :key="p.label">
                        <tr class="border-b border-gray-100 hover:bg-slate-50/30"
                            :class="(p.diffStd < 0 || p.diffVz < -0.001) ? 'bg-red-50/30' : ''">
                            <td class="px-4 py-2.5 font-semibold text-gray-700" x-text="'SJ\u00A0' + p.label"></td>
                            <td class="px-4 py-2.5 text-right text-gray-400 font-mono text-[10px]"
                                x-text="p.anzahl + '\u00A0Mon.'"></td>
                            {{-- Stunden --}}
                            <td class="px-3 py-2.5 text-right text-slate-700 font-semibold border-l border-gray-100"
                                x-text="fmtNum(p.sumSp2, 2)"></td>
                            <td class="px-3 py-2.5 text-right text-amber-700"
                                x-text="fmtNum(p.gesetzl, 2)"></td>
                            <td class="px-3 py-2.5 text-right font-bold"
                                :class="p.diffStd >= 0 ? 'diff-positiv' : 'diff-negativ'"
                                :title="p.diffStd >= 0
                                    ? 'SP2 ≥ gesetzl. Minimum ✓ (' + fmtNum(p.sumSp2,2) + ' h ≥ ' + fmtNum(p.gesetzl,2) + ' h)'
                                    : 'SP2 unter gesetzl. Minimum! (' + fmtNum(p.sumSp2,2) + ' h < ' + fmtNum(p.gesetzl,2) + ' h)'"
                                x-text="fmtSign(p.diffStd, 2)"></td>
                            {{-- VZÄ --}}
                            <td class="px-3 py-2.5 text-right text-slate-600 font-mono border-l border-gray-100"
                                x-text="fmtNum(p.avgVzSp2, 3)"></td>
                            <td class="px-3 py-2.5 text-right text-amber-700 font-mono"
                                x-text="fmtNum(p.avgVzGesetzl, 3)"></td>
                            <td class="px-3 py-2.5 text-right font-semibold font-mono"
                                :class="p.diffVz >= -0.001 ? 'diff-positiv' : 'diff-negativ'"
                                :title="p.diffVz >= -0.001
                                    ? 'VZÄ SP2 ≥ Mindest-VZÄ ✓ (' + fmtNum(p.avgVzSp2,3) + ' ≥ ' + fmtNum(p.avgVzGesetzl,3) + ')'
                                    : 'VZÄ SP2 unter Mindest-VZÄ! (' + fmtNum(p.avgVzSp2,3) + ' < ' + fmtNum(p.avgVzGesetzl,3) + ')'"
                                x-text="fmtSign(p.diffVz, 3)"></td>
                        </tr>
                    </template>
                    <template x-if="sp2Perioden.length === 0">
                        <tr><td colspan="8" class="px-4 py-4 text-center text-gray-400 italic">Keine Monate im Zeitraum</td></tr>
                    </template>
                </tbody>
            </table>
            </div>
            <div class="px-5 py-2.5 bg-gray-50 border-t border-gray-100 space-y-1">
                <div class="flex flex-wrap gap-x-5 gap-y-0.5 text-[10px] text-gray-500">
                    <span><strong class="text-slate-600">Σ SP2</strong> = Jahressumme der mit der Stadt abgerechneten Stunden (SP2)</span>
                    <span><strong class="text-amber-600">Σ Min.</strong> = gesetzl. Mindest-Stunden nach §12 SächsKitaG (Jahressumme)</span>
                    <span><strong class="text-slate-600">Ø VZÄ</strong> = Ø Vollzeitäquivalente je Monat · <strong class="text-amber-600">Ø VZÄ Min.</strong> = gesetzl. Mindest-VZÄ je Monat</span>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-0.5 text-[10px]">
                    <span><span class="diff-positiv font-semibold">+Δ</span> = SP2 über Minimum – Abrechnung deckt gesetzl. Anforderung</span>
                    <span><span class="diff-negativ font-semibold">−Δ</span> = SP2 unter Minimum – gesetzliche Unterdeckung prüfen!</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ── VZÄ-Trend-Chart ──────────────────────────────────────────────── --}}
    @php
        $chartLabels   = $monatsListe->map(fn($m) => $m->monat->locale('de')->isoFormat('MMM YY'))->toArray();
        $chartVzSp1    = $monatsListe->map(fn($m) => round($berechnungenNachMonat->get($m->monat->format('Y-m'))['summe_vz_sp1'] ?? 0, 3))->toArray();
        $chartVzGesetzl= $monatsListe->map(fn($m) => round($berechnungenNachMonat->get($m->monat->format('Y-m'))['summe_gesetz_vz'] ?? 0, 3))->toArray();
        $chartBudget   = $monatsListe->map(fn($m) => round($berechnungenNachMonat->get($m->monat->format('Y-m'))['budget_rest_sp1'] ?? 0, 2))->toArray();
        $chartVzIst    = $monatsListe->map(fn($m) => null)->toArray(); // Ist-Daten nur im Rückblick
    @endphp

    @include('personal.hort_planung._trend_chart', [
        'chartLabels'    => $chartLabels,
        'chartVzSp1'     => $chartVzSp1,
        'chartVzGesetzl' => $chartVzGesetzl,
        'chartBudget'    => $chartBudget,
        'chartVzIst'     => $chartVzIst,
        'chartTitel'     => 'VZÄ-Verlauf: ' . $planung->name,
    ])

    @endif {{-- Ende @if $monatsListe->isNotEmpty() --}}

    {{-- ── Snapshots ────────────────────────────────────────────────────── --}}
    @if($snapshots->isNotEmpty() || $canManage)
    <div class="mt-6 px-1">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <h3 class="text-sm font-semibold text-gray-700">Gespeicherte Snapshots</h3>
            <span class="text-xs text-gray-400">({{ $snapshots->count() }})</span>
            @can('manage hort planung')
            <button @click="showSnapshotModal = true"
                    class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700
                           text-white text-xs font-medium rounded-lg">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Neuer Snapshot
            </button>
            @endcan
        </div>

        @if($snapshots->isEmpty())
        <p class="text-xs text-gray-400 italic px-2">
            Noch keine Snapshots vorhanden. Erstelle einen Snapshot, um den aktuellen Stand einzufrieren.
        </p>
        @else
        <div class="rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <table class="w-full text-xs text-gray-700">
                <thead>
                    <tr class="bg-indigo-50 border-b border-indigo-100 text-left">
                        <th class="px-3 py-2 font-semibold text-indigo-700">Name</th>
                        <th class="px-3 py-2 font-semibold text-indigo-700 hidden sm:table-cell">Erstellt</th>
                        <th class="px-3 py-2 font-semibold text-indigo-700 hidden sm:table-cell">Von</th>
                        <th class="px-3 py-2 font-semibold text-indigo-700 text-center">Monate</th>
                        <th class="px-3 py-2 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach($snapshots as $snap)
                <tr class="hover:bg-indigo-50/30 group">
                    <td class="px-3 py-2.5 font-medium">{{ $snap->name }}</td>
                    <td class="px-3 py-2.5 text-gray-500 hidden sm:table-cell whitespace-nowrap">
                        {{ $snap->created_at->format('d.m.Y, H:i') }} Uhr
                    </td>
                    <td class="px-3 py-2.5 text-gray-500 hidden sm:table-cell">
                        {{ $snap->ersteller?->name ?? '–' }}
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-500">
                        {{ count($snap->daten ?? []) }}
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            {{-- Export --}}
                            <a href="{{ route('hort-planung.exportSnapshot', [$planung, $snap]) }}"
                               title="Als Excel herunterladen"
                               class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-50 hover:bg-emerald-100
                                      text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-medium">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Excel
                            </a>
                            @can('manage hort planung')
                            {{-- Restore --}}
                            <form action="{{ route('hort-planung.restoreSnapshot', [$planung, $snap]) }}"
                                  method="POST"
                                  class="hp-restore-form">
                                @csrf
                                <input type="hidden" name="_snap_name" value="{{ $snap->name }}">
                                <button type="submit"
                                        title="Planung auf diesen Stand zurücksetzen"
                                        class="inline-flex items-center gap-1 px-2 py-1 bg-amber-50 hover:bg-amber-100
                                               text-amber-700 border border-amber-200 rounded-lg text-[11px] font-medium">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                    </svg>
                                    Wiederherstellen
                                </button>
                            </form>
                            {{-- Löschen --}}
                            <form action="{{ route('hort-planung.deleteSnapshot', [$planung, $snap]) }}"
                                  method="POST"
                                  class="hp-delete-snapshot-form">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="_snap_name" value="{{ $snap->name }}">
                                <button type="submit"
                                        title="Snapshot löschen"
                                        class="inline-flex items-center px-2 py-1 bg-red-50 hover:bg-red-100
                                               text-red-600 border border-red-200 rounded-lg text-[11px]">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif

    {{-- ── Modals ──────────────────────────────────────────────────────── --}}
    @include('personal.hort_planung._person_modal')
    @include('personal.hort_planung._bulk_edit_modal')
    @include('personal.hort_planung._snapshot_modal')
    @include('personal.hort_planung._duplicate_modal')

</div>{{-- Ende x-data --}}
</div>{{-- Ende hort-planung-wrapper --}}

@endsection

@push('js')
<script>
/**
 * Alpine.js Component: hortMatrix
 * Neue Features: Zeitraum-Filter, Personen-Aktivitäts-Filter,
 * Propagierung auf Folgemonate, Bulk-Modal Pre-Fill
 */
function hortMatrix(initData) {
    return {
        planungId:     initData.planungId,
        kanBearbeiten: initData.kanBearbeiten,

        // ── Reaktive Daten ───────────────────────────────────────────
        monatData:    initData.monatData,
        personData:   initData.personData,
        zusatzData:   initData.zusatzData,
        berechnungen: initData.berechnungen,

        // Für alleAnzeigen()-Reset
        ersteMk: initData.ersteMk || '',
        letzteMk: initData.letzteMk || '',

        // ── Zeitraum-Filter ──────────────────────────────────────────
        zeitraumVon: initData.ersteMk || '',
        zeitraumBis: initData.letzteMk || '',

        // ── Personen-Filter ──────────────────────────────────────────
        hideInaktivePersonen: false,

        // ── UI-Zustand ───────────────────────────────────────────────
        editing: {},
        saving:  {},

        // Modal-Zustände
        showPersonModal:   false,
        showBulkModal:     false,
        showSnapshotModal: false,
        snapshotName:      '',

        // Bulk-Edit (mit Pre-Fill)
        bulkUserId:   null,
        bulkUserName: '',
        bulkAbMonat:  '',
        bulkSp1:      '',
        bulkSp2:      '',

        // ── Init ────────────────────────────────────────────────────
        init() {
            // Initiale Sichtbarkeit setzen (alle Monate sichtbar)
            this.updateVisibility();
        },

        // ── Zahlenformatierung ───────────────────────────────────────
        fmtNum(v, d = 2) {
            if (v === null || v === undefined || v === '') return '–';
            const n = parseFloat(v);
            if (isNaN(n)) return '–';
            return new Intl.NumberFormat('de-DE', {
                minimumFractionDigits: d,
                maximumFractionDigits: d
            }).format(n);
        },

        fmtSign(v, d = 2) {
            if (v === null || v === undefined || v === '') return '–';
            const n = parseFloat(v);
            if (isNaN(n)) return '–';
            const abs = new Intl.NumberFormat('de-DE', {
                minimumFractionDigits: d,
                maximumFractionDigits: d
            }).format(Math.abs(n));
            return n > 0 ? '+' + abs : (n < 0 ? '-' + abs : abs);
        },

        // ── Zeitraum-Filter ──────────────────────────────────────────
        isMonatSichtbar(mk) {
            if (this.zeitraumVon && mk < this.zeitraumVon) return false;
            if (this.zeitraumBis && mk > this.zeitraumBis) return false;
            return true;
        },

        nurZukunftToggle() {
            const heute = new Date().toISOString().substring(0, 7);
            if (!this.zeitraumVon || this.zeitraumVon <= heute) {
                this.zeitraumVon = heute;
            } else {
                this.alleAnzeigen();
                return;
            }
            this.updateVisibility();
        },

        alleAnzeigen() {
            this.zeitraumVon = this.ersteMk;
            this.zeitraumBis = this.letzteMk;
            this.updateVisibility();
        },

        // Blendet Tabellenspalten via DOM-Manipulation aus;
        // aktualisiert Section-Header-Colspans dynamisch
        updateVisibility() {
            const mks = Object.keys(this.monatData);
            let sichtbar = 0;

            mks.forEach(mk => {
                const show = this.isMonatSichtbar(mk);
                if (show) sichtbar++;
                document.querySelectorAll(`[data-mk="${mk}"]`).forEach(el => {
                    el.style.display = show ? '' : 'none';
                });
            });

            // Section-Header-Colspans aktualisieren (1 sticky + 2 × sichtbare Monate)
            document.querySelectorAll('[data-header-colspan]').forEach(el => {
                el.setAttribute('colspan', 1 + sichtbar * 2);
            });
        },

        // ── Personen-Aktivitäts-Filter ───────────────────────────────
        // Gibt true zurück wenn die Person in mind. einem sichtbaren Monat
        // einen aktiven Vertrag (stunden_vertrag > 0) hat
        hatVertragInSichtbarenMonaten(uid) {
            for (const [key, val] of Object.entries(this.personData)) {
                const sep = key.lastIndexOf('_');
                if (sep < 0) continue;
                if (parseInt(key.substring(sep + 1)) !== uid) continue;
                const mk = key.substring(0, sep);
                if (!this.isMonatSichtbar(mk)) continue;
                if (parseFloat(val.stunden_vertrag || 0) > 0) return true;
            }
            return false;
        },

        // ── Edit-Steuerung ───────────────────────────────────────────
        startEdit(key) {
            if (!this.kanBearbeiten) return;
            this.editing[key] = true;
            this.$nextTick(() => {
                const input = document.querySelector(`[data-edit="${key}"]`);
                if (input) { input.focus(); input.select(); }
            });
        },

        cancelEdit(key) {
            delete this.editing[key];
        },

        // ── AJAX: Monats-Parameter speichern ─────────────────────────
        async saveMonat(monatKey) {
            const data = this.monatData[monatKey];
            if (!data) return;
            Object.keys(this.editing)
                .filter(k => k.endsWith('_' + monatKey))
                .forEach(k => delete this.editing[k]);

            this.saving[monatKey] = true;
            try {
                const resp = await fetch(`/hort-planung/${this.planungId}/monat/${data.id}`, {
                    method: 'PUT',
                    headers: this._headers(),
                    body: JSON.stringify({
                        kinderanzahl:    data.kinderanzahl,
                        vollzeitstunden: data.vollzeitstunden,
                        notiz:           data.notiz,
                    }),
                });
                if (!resp.ok) {
                    const errText = await resp.text();
                    console.warn(`saveMonat HTTP ${resp.status}:`, errText.substring(0, 300));
                    return;
                }
                const json = await resp.json();
                if (json.success) {
                    this.berechnungen[monatKey] = json.berechnungen;
                } else {
                    console.warn('saveMonat Fehler:', json);
                }
            } catch (e) {
                console.error('saveMonat Exception:', e);
            } finally {
                this.saving[monatKey] = false;
            }
        },

        // ── Propagierung: Monat-Parameter auf Folgemonate übertragen ─
        async propagiereMonatParam(vonMk, field) {
            const folgemonate = Object.keys(this.monatData).sort().filter(mk => mk > vonMk);
            const val = this.monatData[vonMk][field];
            // Alpine-State sofort aktualisieren (reaktiv)
            folgemonate.forEach(mk => this.monatData[mk][field] = val);
            // Aktuellen Monat + Folgemonate speichern
            await Promise.allSettled([vonMk, ...folgemonate].map(mk => this.saveMonat(mk)));
        },

        // ── AJAX: Personen-Stunden speichern ─────────────────────────
        async savePerson(monatKey, userId) {
            const pKey   = monatKey + '_' + userId;
            const person = this.personData[pKey];
            if (!person || !person.personId) return;

            ['pg_', 'ps_'].forEach(prefix => delete this.editing[prefix + pKey]);

            // Sicherstellen, dass nur null oder eine valide Zahl gesendet wird.
            // Alpine x-model.number speichert '' wenn das Feld geleert wird.
            const toNum = v => {
                if (v === null || v === undefined || v === '') return null;
                const n = parseFloat(v);
                return isNaN(n) ? null : n;
            };

            this.saving[monatKey] = true;
            try {
                const resp = await fetch(`/hort-planung/${this.planungId}/person/${person.personId}`, {
                    method: 'PUT',
                    headers: this._headers(),
                    body: JSON.stringify({
                        stunden_gesamt: toNum(person.stunden_gesamt),
                        stunden_stadt:  toNum(person.stunden_stadt),
                        kommentar:      person.kommentar ?? null,
                    }),
                });
                if (!resp.ok) {
                    const errText = await resp.text();
                    console.warn(`savePerson HTTP ${resp.status}:`, errText.substring(0, 300));
                    return;
                }
                const json = await resp.json();
                if (json.success) {
                    this.berechnungen[monatKey] = json.berechnungen;
                } else {
                    console.warn('savePerson Fehler (status ' + resp.status + '):', JSON.stringify(json));
                }
            } catch (e) {
                console.error('savePerson Exception:', e);
            } finally {
                this.saving[monatKey] = false;
            }
        },

        // ── AJAX: Zusatzstunden speichern ────────────────────────────
        async saveZusatz(monatKey, typId, monatId) {
            const zKey = monatKey + '_' + typId;
            delete this.editing['zs_' + zKey];

            const toNum = v => {
                if (v === null || v === undefined || v === '') return 0;
                const n = parseFloat(v);
                return isNaN(n) ? 0 : n;
            };

            this.saving[monatKey] = true;
            try {
                const resp = await fetch(`/hort-planung/${this.planungId}/monat/${monatId}/zusatz/${typId}`, {
                    method: 'PUT',
                    headers: this._headers(),
                    body: JSON.stringify({ stunden: toNum(this.zusatzData[zKey]) }),
                });
                if (!resp.ok) {
                    const errText = await resp.text();
                    console.warn(`saveZusatz HTTP ${resp.status}:`, errText.substring(0, 300));
                    return;
                }
                const json = await resp.json();
                if (json.success) {
                    this.berechnungen[monatKey] = json.berechnungen;
                    if (json.zusatz) {
                        this.zusatzData[zKey] = parseFloat(json.zusatz.stunden);
                    }
                }
            } catch (e) {
                console.error('saveZusatz Exception:', e);
            } finally {
                this.saving[monatKey] = false;
            }
        },

        // ── Bulk-Edit Modal öffnen (mit optionalem Pre-Fill) ─────────
        // vonMonatKey: wenn gesetzt, wird ab_monat + aktuelle Stunden vorbelegt
        openBulkEdit(userId, userName, vonMonatKey = '') {
            this.bulkUserId   = userId;
            this.bulkUserName = userName;
            this.bulkAbMonat  = vonMonatKey ? vonMonatKey + '-01' : '';
            if (vonMonatKey) {
                const pKey = vonMonatKey + '_' + userId;
                this.bulkSp1 = this.personData[pKey]?.stunden_gesamt ?? '';
                this.bulkSp2 = this.personData[pKey]?.stunden_stadt  ?? '';
            } else {
                this.bulkSp1 = '';
                this.bulkSp2 = '';
            }
            this.showBulkModal = true;
        },

        // ── Propagierung: Person-Stunden auf Folgemonate übertragen ──
        // field: 'stunden_gesamt' | 'stunden_stadt'
        async propagierePerson(vonMk, userId, field) {
            const pKey = vonMk + '_' + userId;
            const sp1  = (field === 'stunden_gesamt') ? this.personData[pKey]?.stunden_gesamt : undefined;
            const sp2  = (field === 'stunden_stadt')  ? this.personData[pKey]?.stunden_stadt  : undefined;

            // Optimistisches Update des Alpine-States für alle Folgemonate
            const folgemonate = Object.keys(this.monatData).sort().filter(mk => mk > vonMk);
            folgemonate.forEach(mk => {
                const futKey = mk + '_' + userId;
                if (this.personData[futKey] !== undefined) {
                    if (sp1 !== undefined) this.personData[futKey].stunden_gesamt = sp1;
                    if (sp2 !== undefined) this.personData[futKey].stunden_stadt  = sp2;
                }
            });

            // Bulk-API ab aktuellem Monat aufrufen (inkl. vonMk, idempotent)
            const body = { ab_monat: vonMk + '-01' };
            if (sp1 !== undefined) body.stunden_gesamt = sp1;
            if (sp2 !== undefined) body.stunden_stadt  = sp2;

            try {
                const resp = await fetch(
                    `/hort-planung/${this.planungId}/person/${userId}/bulk`,
                    { method: 'PUT', headers: this._headers(), body: JSON.stringify(body) }
                );
                const json = await resp.json();
                if (json.success) {
                    // PersonIds im Alpine-State aktualisieren (für späteres Inline-Speichern)
                    Object.entries(json.personIds || {}).forEach(([mk, pid]) => {
                        const key = mk + '_' + userId;
                        if (this.personData[key]) this.personData[key].personId = pid;
                    });
                    // Berechnungen aller betroffenen Monate aktualisieren
                    Object.entries(json.berechnungen || {}).forEach(([mk, b]) => {
                        this.berechnungen[mk] = b;
                    });
                    // Edit-Modus für aktuellen Monat schließen (da @mousedown.prevent blur verhindert)
                    const pKey = vonMk + '_' + userId;
                    ['pg_', 'ps_'].forEach(prefix => delete this.editing[prefix + pKey]);
                }
            } catch (e) {
                console.error('propagierePerson Fehler:', e);
            }
        },

        // ── Propagierung: Zusatzstunden auf Folgemonate übertragen ───
        async propagiereZusatz(vonMk, typId) {
            const zKey = vonMk + '_' + typId;
            const wert = this.zusatzData[zKey] ?? 0;

            // Optimistisches Update des Alpine-States
            const folgemonate = Object.keys(this.monatData).sort().filter(mk => mk > vonMk);
            folgemonate.forEach(mk => { this.zusatzData[mk + '_' + typId] = wert; });

            // Aktuellen Monat + Folgemonate speichern
            const alleMonate = [vonMk, ...folgemonate];
            await Promise.allSettled(alleMonate.map(mk =>
                this.saveZusatz(mk, typId, this.monatData[mk].id)
            ));
        },

        // ── Budget-Zeitraum Getter: SP1 Vereinsjahr (Aug–Jul) ────────
        get sp1Perioden() {
            const groups = {};
            Object.keys(this.monatData).forEach(mk => {
                const [yr, mo] = mk.split('-').map(Number);
                const label = mo >= 8 ? `${yr}/${yr + 1}` : `${yr - 1}/${yr}`;
                if (!groups[label]) groups[label] = [];
                groups[label].push(mk);
            });
            return Object.entries(groups)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([label, mks]) => {
                    let sumSp1 = 0, budget = 0, sumVertrag = 0;
                    mks.sort().forEach(mk => {
                        sumSp1     += this.berechnungen[mk]?.summe_sp1      ?? 0;
                        budget     += this.berechnungen[mk]?.budget_gesamt  ?? 0;
                        sumVertrag += this.berechnungen[mk]?.summe_vertrag  ?? 0;
                    });
                    const rest      = budget - sumSp1;
                    const abwVertrag = Math.round((sumSp1 - sumVertrag) * 100) / 100;
                    return {
                        label,
                        anzahl:     mks.length,
                        sumSp1:     Math.round(sumSp1     * 100) / 100,
                        budget:     Math.round(budget      * 100) / 100,
                        rest:       Math.round(rest        * 100) / 100,
                        sumVertrag: Math.round(sumVertrag  * 100) / 100,
                        abwVertrag,
                    };
                });
        },

        // ── Budget-Zeitraum Getter: SP2 Stadtjahr (Jan–Dez) ─────────
        get sp2Perioden() {
            const groups = {};
            Object.keys(this.monatData).forEach(mk => {
                const year = mk.substring(0, 4);
                if (!groups[year]) groups[year] = [];
                groups[year].push(mk);
            });
            return Object.entries(groups)
                .sort()
                .map(([year, mks]) => {
                    let sumSp2 = 0, gesetzl = 0, sumVzSp2 = 0, sumVzGesetzl = 0;
                    mks.sort().forEach(mk => {
                        sumSp2       += this.berechnungen[mk]?.summe_sp2              ?? 0;
                        gesetzl      += this.berechnungen[mk]?.summe_stunden_gesetzl   ?? 0;
                        sumVzSp2     += this.berechnungen[mk]?.summe_vz_sp2             ?? 0;
                        sumVzGesetzl += this.berechnungen[mk]?.summe_gesetz_vz          ?? 0;
                    });
                    const diffStd      = sumSp2 - gesetzl;
                    const avgVzSp2     = mks.length > 0 ? sumVzSp2    / mks.length : 0;
                    const avgVzGesetzl = mks.length > 0 ? sumVzGesetzl / mks.length : 0;
                    const diffVz       = avgVzSp2 - avgVzGesetzl;
                    return {
                        label:         year,
                        anzahl:        mks.length,
                        sumSp2:        Math.round(sumSp2        * 100) / 100,
                        gesetzl:       Math.round(gesetzl        * 100) / 100,
                        diffStd:       Math.round(diffStd        * 100) / 100,
                        avgVzSp2:      Math.round(avgVzSp2      * 1000) / 1000,
                        avgVzGesetzl:  Math.round(avgVzGesetzl  * 1000) / 1000,
                        diffVz:        Math.round(diffVz        * 1000) / 1000,
                    };
                });
        },

        // ── Privater Helper: CSRF/JSON Headers ──────────────────────
        _headers() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };
        },
    };
}
</script>
<script>
// Restore- und Löschen-Bestätigung für Snapshots
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.hp-restore-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var name = form.querySelector('[name="_snap_name"]')?.value ?? 'diesen Snapshot';
            var msg  = 'Planung auf Snapshot "' + name + '" zuruecksetzen?\n\n'
                     + 'Alle aktuellen Personenstunden und Monatsparameter werden ueberschrieben.\n'
                     + 'Zusatzstunden bleiben erhalten.\n\nFortfahren?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });
    document.querySelectorAll('.hp-delete-snapshot-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var name = form.querySelector('[name="_snap_name"]')?.value ?? 'diesen Snapshot';
            if (!confirm('Snapshot "' + name + '" wirklich loeschen?')) {
                e.preventDefault();
            }
        });
    });
});
</script>
@endpush

