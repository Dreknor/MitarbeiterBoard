@extends('layouts.app')

@section('title', 'Rückblick – ' . $planung->name)
@section('site-title', 'Hortstunden-Planung')

@push('css')
    @vite(['resources/css/hort-planung.css'])
@endpush

@section('content')
<div class="hort-planung-wrapper">
<div class="max-w-full px-4 py-6">

@php
    // ── Daten aufbereiten ──────────────────────────────────────────────────────

    // Alle einzigartigen Personen über alle vergangenen Monate sammeln
    $allePersonen = $monate
        ->flatMap(fn($m) => $m->personen)
        ->unique('user_id')
        ->sortBy(fn($p) => $p->user?->name ?? 'zzz')
        ->values();

    // Personen indexiert nach Monat-Key und User-ID für O(1)-Zugriff
    // Wert: Collection<userId => HortPlanungPerson>
    $personenNachMonat = $monate->mapWithKeys(fn($m) => [
        $m->monat->format('Y-m') => $m->personen->keyBy('user_id'),
    ]);

    // Monatsliste als indexiertes Array für einheitliche Iteration
    $monatsListe = $monate->sortBy('monat')->values();

    // ── Chart-Daten vorbereiten ────────────────────────────────────────────────
    $chartLabels   = [];
    $chartVzSp1    = [];
    $chartVzGesetzl = [];
    $chartBudget   = [];
    $chartVzIst    = [];

    foreach ($monatsListe as $m) {
        $key = $m->monat->format('Y-m');
        $ber = $berechnungenNachMonat->get($key) ?? [];

        $chartLabels[]    = $m->monat->locale('de')->isoFormat('MMM YY');
        $chartVzSp1[]     = isset($ber['summe_vz_sp1'])   ? round($ber['summe_vz_sp1'], 3)   : null;
        $chartVzGesetzl[] = isset($ber['summe_gesetz_vz']) ? round($ber['summe_gesetz_vz'], 3) : null;
        $chartBudget[]    = isset($ber['budget_rest_sp1']) ? round($ber['budget_rest_sp1'], 2) : null;

        $summeIst = $ber['summe_ist'] ?? 0;
        $vz = $m->vollzeitstunden > 0 ? $m->vollzeitstunden : 40;
        $chartVzIst[] = $summeIst > 0 ? round($summeIst / $vz, 3) : null;
    }

    // ── Hilfsfunktionen ────────────────────────────────────────────────────────
    $fmt = fn(?float $v, int $d = 2): string =>
        $v !== null ? number_format($v, $d, ',', '.') : '–';

    $fmtSign = fn(?float $v, int $d = 2): string =>
        $v !== null ? (($v > 0 ? '+' : '') . number_format($v, $d, ',', '.')) : '–';

    // Differenz-CSS: positiv = rot (Soll > Ist = Untererfüllung), negativ = grün (Übererfüllung)
    $istDiffCss = fn(?float $v): string => match(true) {
        $v === null || abs($v) < 0.005 => 'text-gray-400',
        $v > 0 => 'diff-negativ',
        default => 'diff-positiv',
    };

    // Vertrags-Differenz: neutral (nur informativ)
    $vertragDiffCss = fn(?float $v): string =>
        ($v === null || abs($v) < 0.005) ? 'text-gray-400' : 'text-amber-600 font-medium';

    // Budget/Puffer CSS
    $budgetCss = fn(?float $v): string => match(true) {
        $v === null => 'text-gray-400',
        $v >= 0    => 'budget-positiv',
        default    => 'budget-negativ',
    };
@endphp

    {{-- ── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="flex items-center gap-1.5 text-sm text-gray-500 mb-5" aria-label="Breadcrumb">
        <a href="{{ route('hort-planung.index') }}" class="hover:text-blue-600">Hortstunden-Planung</a>
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <a href="{{ route('hort-planung.show', $planung) }}"
           class="hover:text-blue-600 truncate max-w-xs">{{ $planung->name }}</a>
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-800 font-medium">Rückblick</span>
    </nav>

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span class="text-2xl">📊</span>
                Soll-Ist-Vergleich
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                <span class="font-medium text-gray-700">{{ $planung->name }}</span>
                &middot; {{ $planung->department?->name ?? '–' }}
                &middot; {{ $monatsListe->count() }} vergangene
                {{ $monatsListe->count() === 1 ? 'Monat' : 'Monate' }}
                @if($planung->typ === 'rueckblick')
                    <span class="badge-rueckblick ml-2">Rückblick</span>
                @endif
            </p>
        </div>

        @can('manage hort planung')
        <div class="flex flex-wrap gap-2 shrink-0">
            {{-- Ist-Stunden aus Zeiterfassung laden --}}
            <form method="POST" action="{{ route('hort-planung.syncIstStunden', $planung) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-purple-50 hover:bg-purple-100
                               text-purple-700 text-sm font-medium rounded-xl border border-purple-200 shadow-sm">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Ist-Stunden aus Zeiterfassung laden
                </button>
            </form>

            {{-- Vertragsstunden aktualisieren --}}
            <form method="POST" action="{{ route('hort-planung.syncVertrag', $planung) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-50 hover:bg-blue-100
                               text-blue-700 text-sm font-medium rounded-xl border border-blue-200 shadow-sm">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Vertragsstunden aktualisieren
                </button>
            </form>
        </div>
        @endcan
    </div>

    {{-- ── Flash-Meldung ───────────────────────────────────────────────────── --}}
    @if(session('Meldung'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium
                {{ session('type') === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' :
                   (session('type') === 'danger'  ? 'bg-red-50 text-red-800 border border-red-200' :
                    'bg-blue-50 text-blue-800 border border-blue-200') }}"
         role="alert">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- ── Leerzustand ─────────────────────────────────────────────────────── --}}
    @if($monatsListe->isEmpty())
    <div class="text-center py-16 bg-white rounded-2xl border border-gray-200 shadow-sm">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <p class="text-sm font-medium text-gray-600">
            Noch keine vergangenen Monate vorhanden.
        </p>
        <p class="text-xs text-gray-400 mt-1">
            Vergangene Monate werden automatisch angezeigt, sobald der Planungszeitraum begonnen hat.
        </p>
    </div>
    @else

    {{-- ── Erklärung der Spalten ───────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-gray-500 mb-3 px-1">
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-blue-100 border border-blue-300"></span>
            <strong class="text-gray-600">Soll</strong> – geplante Stunden (SP1)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-slate-100 border border-slate-300"></span>
            <strong class="text-gray-600">Vertrag</strong> – Vertragsstunden aus Anstellung
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-emerald-100 border border-emerald-300"></span>
            <strong class="text-gray-600">Ist</strong> – geleistete Stunden aus Zeiterfassung
        </span>
    </div>

    {{-- ── Haupt-Tabelle ────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse" style="min-width: {{ 220 + $monatsListe->count() * 195 }}px">

                {{-- ── THEAD ──────────────────────────────────────────────── --}}
                <thead>
                    {{-- Monatsnamen-Zeile --}}
                    <tr class="border-b border-gray-200">
                        {{-- Sticky-Spalte: leer --}}
                        <th scope="col"
                            class="sticky left-0 z-20 bg-gray-50 border-r border-gray-200 w-52 min-w-52 px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide text-[10px]">
                            Person / Kennzahl
                        </th>
                        @foreach($monatsListe as $monat)
                        @php $monatKey = $monat->monat->format('Y-m'); @endphp
                        <th colspan="3"
                            class="px-2 py-2 text-center font-semibold text-gray-700 border-r border-gray-100 bg-gray-50
                                   {{ now()->startOfMonth()->eq($monat->monat) ? 'bg-blue-50 text-blue-800' : '' }}">
                            {{ $monat->monat->locale('de')->isoFormat('MMMM YYYY') }}
                            @if($monat->notiz)
                                <span class="ml-1 text-amber-500 cursor-help" title="{{ $monat->notiz }}">ℹ</span>
                            @endif
                        </th>
                        @endforeach
                    </tr>

                    {{-- Spalten-Header: Soll / Vertrag / Ist --}}
                    <tr class="border-b-2 border-gray-200 bg-gray-50">
                        <th class="sticky left-0 z-20 bg-gray-50 border-r border-gray-200 px-3 py-1.5"></th>
                        @foreach($monatsListe as $monat)
                        <th class="px-2 py-1.5 text-center font-medium text-blue-700 bg-blue-50/50 border-r border-gray-100/50 w-16 min-w-16">
                            Soll
                        </th>
                        <th class="px-2 py-1.5 text-center font-medium text-slate-600 bg-slate-50/50 border-r border-gray-100/50 w-16 min-w-16">
                            Vertrag
                        </th>
                        <th class="px-2 py-1.5 text-center font-medium text-emerald-700 bg-emerald-50/50 border-r border-gray-200 w-16 min-w-16">
                            Ist
                        </th>
                        @endforeach
                    </tr>
                </thead>

                {{-- ── TBODY: Personen ─────────────────────────────────────── --}}
                <tbody>
                    @forelse($allePersonen as $idx => $person)
                    @php
                        $userId   = $person->user_id;
                        $userName = $person->user?->name ?? 'Unbekannt (#' . $userId . ')';

                        // Kommentar aus dem jüngsten Monat ermitteln (letzter Eintrag mit Kommentar)
                        $kommentarEintrag = null;
                        foreach ($monatsListe->sortByDesc('monat') as $checkMonat) {
                            $checkKey = $checkMonat->monat->format('Y-m');
                            $checkP   = $personenNachMonat->get($checkKey)?->get($userId);
                            if ($checkP?->kommentar) {
                                $kommentarEintrag = $checkP;
                                break;
                            }
                        }
                    @endphp
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }} hover:bg-blue-50/30 border-b border-gray-100">

                        {{-- Personenname (sticky) --}}
                        <td class="sticky left-0 z-10 border-r border-gray-200 px-3 py-2 font-medium text-gray-800
                                   {{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }} hover:bg-blue-50/30">
                            {{ $userName }}
                        </td>

                        @foreach($monatsListe as $monat)
                        @php
                            $mk      = $monat->monat->format('Y-m');
                            $eintrag = $personenNachMonat->get($mk)?->get($userId);

                            $soll    = $eintrag?->stunden_gesamt;
                            $vertrag = $eintrag?->stunden_vertrag;
                            $ist     = $eintrag?->stunden_ist;
                        @endphp
                        {{-- Soll --}}
                        <td class="px-2 py-2 text-center text-gray-700 bg-blue-50/20">
                            {{ $soll !== null ? number_format($soll, 1, ',', '.') : '–' }}
                        </td>
                        {{-- Vertrag --}}
                        <td class="px-2 py-2 text-center text-gray-500 bg-slate-50/20">
                            @if($vertrag !== null)
                                @php $abw = $soll !== null ? ($soll - $vertrag) : null; @endphp
                                <span class="{{ $abw !== null && abs($abw) >= 0.01 ? 'text-amber-600 font-medium' : '' }}">
                                    {{ number_format($vertrag, 1, ',', '.') }}
                                </span>
                                @if($abw !== null && abs($abw) >= 0.01)
                                    <br>
                                    <span class="text-[10px] {{ $abw > 0 ? 'text-amber-500' : 'text-emerald-500' }}">
                                        {{ $abw > 0 ? '+' : '' }}{{ number_format($abw, 1, ',', '.') }}
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-300">–</span>
                            @endif
                        </td>
                        {{-- Ist --}}
                        <td class="px-2 py-2 text-center bg-emerald-50/20 border-r border-gray-200">
                            @if($ist !== null)
                                @php $abw = $soll !== null ? ($soll - $ist) : null; @endphp
                                <span class="{{ $abw !== null && abs($abw) >= 0.01 ? ($abw > 0 ? 'text-red-600' : 'text-emerald-600') : 'text-gray-600' }}
                                             {{ abs($abw ?? 0) >= 0.01 ? 'font-medium' : '' }}">
                                    {{ number_format($ist, 1, ',', '.') }}
                                </span>
                                @if($abw !== null && abs($abw) >= 0.01)
                                    <br>
                                    <span class="text-[10px] {{ $abw > 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                        {{ $abw > 0 ? '+' : '' }}{{ number_format($abw, 1, ',', '.') }}
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-300">–</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>

                    @if($kommentarEintrag?->kommentar)
                    <tr class="border-b border-gray-100 {{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}">
                        <td class="sticky left-0 z-10 px-3 py-0.5 text-[10px] text-amber-600 italic border-r border-gray-200
                                   {{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}">
                            └ {{ $kommentarEintrag->kommentar }}
                        </td>
                        <td colspan="{{ $monatsListe->count() * 3 }}"></td>
                    </tr>
                    @endif

                    @empty
                    <tr>
                        <td class="sticky left-0 z-10 bg-white border-r border-gray-200 px-3 py-4 text-gray-400 italic"
                            colspan="{{ 1 + $monatsListe->count() * 3 }}">
                            Noch keine Personen in dieser Planung vorhanden.
                        </td>
                    </tr>
                    @endforelse

                    {{-- ── Trennzeile --}}
                    <tr class="border-b-2 border-gray-300">
                        <td colspan="{{ 1 + $monatsListe->count() * 3 }}" class="h-0 p-0"></td>
                    </tr>

                    {{-- ── Summen-Zeile --}}
                    <tr class="bg-gray-100 font-semibold border-b border-gray-200">
                        <td class="sticky left-0 z-10 bg-gray-100 border-r border-gray-200 px-3 py-2 text-gray-700 uppercase tracking-wide text-[10px]">
                            Summe Stunden
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $mk  = $monat->monat->format('Y-m');
                            $ber = $berechnungenNachMonat->get($mk) ?? [];
                            $sp1 = $ber['summe_sp1'] ?? null;
                            $sv  = $ber['summe_vertrag'] ?? null;
                            $si  = $ber['summe_ist'] ?? null;
                        @endphp
                        <td class="px-2 py-2 text-center text-blue-700 bg-blue-50/40">
                            {{ $sp1 !== null ? number_format($sp1, 1, ',', '.') : '–' }}
                        </td>
                        <td class="px-2 py-2 text-center text-slate-600 bg-slate-50/40">
                            {{ $sv !== null ? number_format($sv, 1, ',', '.') : '–' }}
                        </td>
                        <td class="px-2 py-2 text-center text-emerald-700 bg-emerald-50/40 border-r border-gray-200">
                            {{ $si !== null ? number_format($si, 1, ',', '.') : '–' }}
                        </td>
                        @endforeach
                    </tr>

                    {{-- ── Abweichung Soll ↔ Vertrag --}}
                    <tr class="bg-white border-b border-gray-100">
                        <td class="sticky left-0 z-10 bg-white border-r border-gray-200 px-3 py-1.5 text-gray-500 text-[11px]">
                            Abw. Soll ↔ Vertrag
                            <span class="ml-1 text-gray-300 text-[9px]">(Soll − Vertrag)</span>
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $mk  = $monat->monat->format('Y-m');
                            $ber = $berechnungenNachMonat->get($mk) ?? [];
                            $abw = $ber['abweichung_soll_vertrag'] ?? null;
                        @endphp
                        <td class="px-2 py-1.5 text-center bg-blue-50/10
                                   {{ $abw !== null && abs($abw) >= 0.01 ? 'text-amber-600 font-medium' : 'text-gray-400' }}">
                            {{ $abw !== null ? (($abw > 0 ? '+' : '') . number_format($abw, 1, ',', '.')) : '–' }}
                        </td>
                        <td class="px-2 py-1.5 bg-slate-50/10"></td>
                        <td class="px-2 py-1.5 bg-emerald-50/10 border-r border-gray-200"></td>
                        @endforeach
                    </tr>

                    {{-- ── Abweichung Soll ↔ Ist --}}
                    <tr class="bg-white border-b border-gray-200">
                        <td class="sticky left-0 z-10 bg-white border-r border-gray-200 px-3 py-1.5 text-gray-500 text-[11px]">
                            Abw. Soll ↔ Ist
                            <span class="ml-1 text-gray-300 text-[9px]">(Soll − Ist)</span>
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $mk  = $monat->monat->format('Y-m');
                            $ber = $berechnungenNachMonat->get($mk) ?? [];
                            $abw = $ber['abweichung_soll_ist'] ?? null;
                        @endphp
                        <td class="px-2 py-1.5 bg-blue-50/10"></td>
                        <td class="px-2 py-1.5 bg-slate-50/10"></td>
                        <td class="px-2 py-1.5 text-center border-r border-gray-200 bg-emerald-50/10 font-medium
                                   {{ $abw === null || abs($abw) < 0.01 ? 'text-gray-400' : ($abw > 0 ? 'text-red-600' : 'text-emerald-600') }}">
                            {{ $abw !== null ? (($abw > 0 ? '+' : '') . number_format($abw, 1, ',', '.')) : '–' }}
                        </td>
                        @endforeach
                    </tr>

                    {{-- ── Zweite Trennzeile --}}
                    <tr class="border-b-2 border-gray-300">
                        <td colspan="{{ 1 + $monatsListe->count() * 3 }}" class="h-0 p-0"></td>
                    </tr>

                    {{-- ── Gesetzliches Minimum --}}
                    <tr class="bg-amber-50/40 border-b border-gray-100">
                        <td class="sticky left-0 z-10 bg-amber-50/60 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                            <span>⚖ Gesetzl. Minimum</span>
                            <span class="block text-[10px] text-gray-400 font-normal">Stunden (SP1-Referenz)</span>
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $mk  = $monat->monat->format('Y-m');
                            $ber = $berechnungenNachMonat->get($mk) ?? [];
                            $min = $ber['summe_stunden_gesetzl'] ?? null;
                        @endphp
                        <td class="px-2 py-2 text-center text-amber-800 font-medium">
                            {{ $min !== null ? number_format($min, 1, ',', '.') : '–' }}
                        </td>
                        <td class="px-2 py-2 bg-slate-50/10"></td>
                        <td class="px-2 py-2 bg-emerald-50/10 border-r border-gray-200">
                            {{-- Gesetzl. Minimum bezogen auf Ist: summe_ist - summe_stunden_gesetzl --}}
                        </td>
                        @endforeach
                    </tr>

                    {{-- ── Puffer über Minimum --}}
                    <tr class="bg-white border-b border-gray-100">
                        <td class="sticky left-0 z-10 bg-white border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                            <span>📈 Puffer über Minimum</span>
                            <span class="block text-[10px] text-gray-400 font-normal">Plan-Soll / Ist vs. Minimum</span>
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $mk  = $monat->monat->format('Y-m');
                            $ber = $berechnungenNachMonat->get($mk) ?? [];

                            // Soll-Puffer = Budget-Rest (SP1) aus Berechnung
                            $pufferSoll = $ber['budget_rest_sp1'] ?? null;

                            // Ist-Puffer = summe_ist − gesetzl. Minimum
                            $pufferIst = (isset($ber['summe_ist'], $ber['summe_stunden_gesetzl']) && $ber['summe_ist'] > 0)
                                ? round($ber['summe_ist'] - $ber['summe_stunden_gesetzl'], 2)
                                : null;
                        @endphp
                        {{-- Soll-Puffer --}}
                        <td class="px-2 py-2 text-center font-semibold
                                   {{ $pufferSoll === null ? 'text-gray-400' : ($pufferSoll >= 0 ? 'text-emerald-700' : 'text-red-600') }}">
                            {{ $pufferSoll !== null ? (($pufferSoll >= 0 ? '+' : '') . number_format($pufferSoll, 1, ',', '.')) : '–' }}
                        </td>
                        <td class="px-2 py-2 bg-slate-50/10"></td>
                        {{-- Ist-Puffer --}}
                        <td class="px-2 py-2 text-center font-semibold border-r border-gray-200
                                   {{ $pufferIst === null ? 'text-gray-300' : ($pufferIst >= 0 ? 'text-emerald-700' : 'text-red-600') }}">
                            {{ $pufferIst !== null ? (($pufferIst >= 0 ? '+' : '') . number_format($pufferIst, 1, ',', '.')) : '–' }}
                        </td>
                        @endforeach
                    </tr>

                    {{-- ── VZÄ-Zusammenfassung --}}
                    <tr class="bg-blue-50/30 border-t-2 border-gray-200">
                        <td class="sticky left-0 z-10 bg-blue-50/50 border-r border-gray-200 px-3 py-2 text-gray-600 text-[11px] font-medium">
                            VZÄ gesamt (Verein/SP1)
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $mk  = $monat->monat->format('Y-m');
                            $ber = $berechnungenNachMonat->get($mk) ?? [];
                            $vzSp1    = $ber['summe_vz_sp1'] ?? null;
                            $vzGesetzl = $ber['summe_gesetz_vz'] ?? null;
                        @endphp
                        <td class="px-2 py-2 text-center text-blue-700 font-medium">
                            {{ $vzSp1 !== null ? number_format($vzSp1, 3, ',', '.') : '–' }}
                        </td>
                        <td class="px-2 py-2 text-center text-slate-500 text-[10px]">
                            <span class="text-gray-400">Min:</span>
                            {{ $vzGesetzl !== null ? number_format($vzGesetzl, 3, ',', '.') : '–' }}
                        </td>
                        <td class="px-2 py-2 border-r border-gray-200">
                            @php
                                $vzIst = (isset($ber['summe_ist']) && $ber['summe_ist'] > 0 && $monat->vollzeitstunden > 0)
                                    ? round($ber['summe_ist'] / $monat->vollzeitstunden, 3)
                                    : null;
                            @endphp
                            <span class="text-center block {{ $vzIst !== null ? 'text-emerald-700 font-medium' : 'text-gray-300' }}">
                                {{ $vzIst !== null ? number_format($vzIst, 3, ',', '.') : '–' }}
                            </span>
                        </td>
                        @endforeach
                    </tr>

                </tbody>
            </table>
        </div>

        {{-- Legende --}}
        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/80 flex flex-wrap gap-x-5 gap-y-1 text-[10px] text-gray-500">
            <span>
                <strong>Abw.</strong> Soll ↔ Vertrag: positive Werte = mehr geplant als vertraglich,
                negative = weniger geplant als vertraglich (amber = Abweichung vorhanden)
            </span>
            <span>
                <strong>Abw.</strong> Soll ↔ Ist: positive Werte = Planstunden nicht erreicht
                <span class="text-red-500">(rot)</span>,
                negative = mehr geleistet als geplant
                <span class="text-emerald-500">(grün)</span>
            </span>
            <span>
                <strong>Puffer</strong>: Differenz aus geplanten bzw. geleisteten Stunden zum gesetzlichen Minimum
            </span>
        </div>
    </div>

    {{-- ── VZÄ-Trend-Chart ─────────────────────────────────────────────────── --}}
    @include('personal.hort_planung._trend_chart', [
        'chartLabels'    => $chartLabels,
        'chartVzSp1'     => $chartVzSp1,
        'chartVzGesetzl' => $chartVzGesetzl,
        'chartBudget'    => $chartBudget,
        'chartVzIst'     => $chartVzIst,
        'chartTitel'     => 'VZÄ-Verlauf – Soll vs. Ist vs. Gesetzliches Minimum',
    ])

    {{-- ── Kinder- und Vollzeitinfo pro Monat ─────────────────────────────── --}}
    <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
        @foreach($monatsListe->take(12) as $monat)
        @php
            $mk  = $monat->monat->format('Y-m');
            $ber = $berechnungenNachMonat->get($mk) ?? [];
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-3 text-center">
            <div class="text-xs font-semibold text-gray-700 mb-1">
                {{ $monat->monat->locale('de')->isoFormat('MMM YY') }}
            </div>
            <div class="text-[10px] text-gray-500 space-y-0.5">
                <div>{{ $monat->kinderanzahl }} Kinder</div>
                <div>VZ: {{ number_format($monat->vollzeitstunden, 0, ',', '.') }}h</div>
                @if(isset($ber['summe_gesetz_vz']))
                <div class="text-amber-600">⚖ {{ number_format($ber['summe_gesetz_vz'], 2, ',', '.') }} VZÄ</div>
                @endif
                @if(isset($ber['budget_rest_sp1']))
                <div class="{{ $ber['budget_rest_sp1'] >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-semibold">
                    {{ $ber['budget_rest_sp1'] >= 0 ? '+' : '' }}{{ number_format($ber['budget_rest_sp1'], 1, ',', '.') }}h
                </div>
                @endif
            </div>
        </div>
        @endforeach
        @if($monatsListe->count() > 12)
        <div class="bg-gray-50 rounded-xl border border-dashed border-gray-200 p-3 text-center flex items-center justify-center">
            <span class="text-xs text-gray-400">+{{ $monatsListe->count() - 12 }} weitere Monate</span>
        </div>
        @endif
    </div>

    @endif {{-- Ende @if $monatsListe->isNotEmpty() --}}

</div>
</div>
@endsection













