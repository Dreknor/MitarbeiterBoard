@extends('layouts.app')

@section('title', 'Vergleich: ' . $planung->name . ' ↔ ' . $other->name)
@section('site-title', 'Hortstunden-Planung')

@push('css')
    @vite(['resources/css/hort-planung.css'])
@endpush

@section('content')
<div class="hort-planung-wrapper">
<div class="max-w-full px-4 py-6">

@php
    // ── Daten aufbereiten ──────────────────────────────────────────────────────
    $vergleichListe = $vergleich->values()->sortBy('monat');

    // Alle einzigartigen Personen aus Plan A für Personenzeilen
    $personenA = collect();
    foreach ($planung->monate as $m) {
        foreach ($m->personen as $p) {
            $personenA[$p->user_id] = $p->user?->name ?? 'Unbekannt (#' . $p->user_id . ')';
        }
    }
    $personenA = $personenA->sortBy(fn($n) => $n);

    // Personen-Daten (Plan A + Plan B) indexiert für O(1)-Zugriff
    $pDataA = [];
    foreach ($planung->monate as $m) {
        $mk = $m->monat->format('Y-m');
        foreach ($m->personen as $p) {
            $pDataA[$mk][$p->user_id] = $p;
        }
    }
    $pDataB = [];
    foreach ($other->monate as $m) {
        $mk = $m->monat->format('Y-m');
        foreach ($m->personen as $p) {
            $pDataB[$mk][$p->user_id] = $p;
        }
    }

    // Hilfsfunktionen
    $fmt = fn(?float $v, int $d = 2): string =>
        $v !== null ? number_format($v, $d, ',', '.') : '–';

    $fmtSign = fn(?float $v, int $d = 2): string =>
        $v !== null ? (($v > 0 ? '+' : '') . number_format($v, $d, ',', '.')) : '–';

    $diffCss = fn(?float $v): string => match(true) {
        $v === null || abs($v) < 0.005 => 'hp-diff-zero',
        $v > 0  => 'hp-diff-pos',
        default => 'hp-diff-neg',
    };

    $tableMinWidth = 260 + $vergleichListe->count() * 220;

    // Chart-Daten für beide Planungen
    $chartLabels = [];
    $chartVzA = $chartVzB = $chartGesetzlA = $chartGesetzlB = [];
    foreach ($vergleichListe as $e) {
        $chartLabels[]   = \Carbon\Carbon::createFromFormat('Y-m', $e['monat'])->locale('de')->isoFormat('MMM YY');
        $chartVzA[]      = round($e['planung_a']['summe_vz_sp1'] ?? 0, 3);
        $chartVzB[]      = round($e['planung_b']['summe_vz_sp1'] ?? 0, 3);
        $chartGesetzlA[] = round($e['planung_a']['summe_gesetz_vz'] ?? 0, 3);
        $chartGesetzlB[] = round($e['planung_b']['summe_gesetz_vz'] ?? 0, 3);
    }
@endphp

    {{-- ── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="flex items-center gap-1.5 text-sm text-gray-500 mb-5" aria-label="Breadcrumb">
        <a href="{{ route('hort-planung.index') }}" class="hover:text-blue-600">Hortstunden-Planung</a>
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <a href="{{ route('hort-planung.show', $planung) }}" class="hover:text-blue-600 truncate max-w-xs">
            {{ $planung->name }}
        </a>
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-800 font-medium">Vergleich</span>
    </nav>

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span class="text-2xl">↔</span>
                Szenarien-Vergleich
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                <span class="font-semibold text-blue-700">Plan A:</span>
                {{ $planung->name }}
                &nbsp;↔&nbsp;
                <span class="font-semibold text-emerald-700">Plan B:</span>
                {{ $other->name }}
                &middot; {{ $vergleichListe->count() }} gemeinsame Monate
            </p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('hort-planung.show', $planung) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700
                      text-xs font-medium rounded-xl border border-blue-200 shadow-sm">
                ← Plan A anzeigen
            </a>
            <a href="{{ route('hort-planung.show', $other) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700
                      text-xs font-medium rounded-xl border border-emerald-200 shadow-sm">
                Plan B anzeigen →
            </a>
        </div>
    </div>

    {{-- ── Flash-Meldung ───────────────────────────────────────────────────── --}}
    @if(session('Meldung'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium
                {{ session('type') === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' :
                   'bg-blue-50 text-blue-800 border border-blue-200' }}"
         role="alert">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- ── Leerzustand ─────────────────────────────────────────────────────── --}}
    @if($vergleichListe->isEmpty())
    <div class="text-center py-16 bg-white rounded-2xl border border-gray-200 shadow-sm">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
        </svg>
        <p class="text-sm font-medium text-gray-600">Keine gemeinsamen Monate gefunden.</p>
        <p class="text-xs text-gray-400 mt-1">
            Die beiden Planungen haben keine überlappenden Zeiträume.
        </p>
    </div>
    @else

    {{-- ── Legende ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-gray-500 mb-3 px-1">
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-4 h-3 rounded-sm bg-blue-100 border border-blue-300"></span>
            <strong class="text-blue-700">Plan A</strong> – {{ $planung->name }}
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-4 h-3 rounded-sm bg-emerald-100 border border-emerald-300"></span>
            <strong class="text-emerald-700">Plan B</strong> – {{ $other->name }}
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-4 h-3 rounded-sm bg-gray-100 border border-gray-300"></span>
            <strong class="text-gray-600">Δ</strong> – A minus B (positiv = A höher)
        </span>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ══  VERGLEICHSTABELLE                                              ══ --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hp-matrix-outer-wrap mb-6">
        <div class="hp-matrix-scroll"
             x-init="$nextTick(() => {
                 const r1 = $el.querySelector('thead tr:first-child');
                 if (r1) $el.style.setProperty('--hp-thead-r1-h', r1.offsetHeight + 'px');
             })">
        <table class="w-full text-xs border-collapse" style="min-width: {{ $tableMinWidth }}px">

            {{-- THEAD --}}
            <thead>
                {{-- Monatsnamen --}}
                <tr class="border-b border-gray-200">
                    <th class="sticky left-0 z-20 bg-gray-100 border-r border-gray-200 w-60 min-w-60 px-3 py-2.5
                               text-left text-[10px] font-semibold text-gray-600 uppercase tracking-wide">
                        Kennzahl
                    </th>
                    @foreach($vergleichListe as $e)
                    <th colspan="3"
                        class="px-2 py-2.5 text-center font-semibold text-gray-700 border-r border-gray-200 bg-gray-50">
                        {{ \Carbon\Carbon::createFromFormat('Y-m', $e['monat'])->locale('de')->isoFormat('MMMM YYYY') }}
                    </th>
                    @endforeach
                </tr>
                {{-- A / B / Δ Subheader --}}
                <tr class="border-b-2 border-gray-300 bg-gray-50">
                    <th class="sticky left-0 z-20 bg-gray-50 border-r border-gray-200 px-3 py-1.5"></th>
                    @foreach($vergleichListe as $e)
                    <th class="px-2 py-1.5 text-center text-[10px] font-semibold text-blue-700 bg-blue-50/50 w-20 min-w-20">Plan A</th>
                    <th class="px-2 py-1.5 text-center text-[10px] font-semibold text-emerald-700 bg-emerald-50/50 w-20 min-w-20">Plan B</th>
                    <th class="px-2 py-1.5 text-center text-[10px] font-semibold text-gray-600 bg-gray-100/80 w-16 min-w-16 border-r border-gray-200">Δ (A-B)</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>

            {{-- ── Globalparameter ───────────────────────────────────────── --}}
            <tr>
                <td colspan="{{ 1 + $vergleichListe->count() * 3 }}"
                    class="sticky left-0 bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Parameter
                </td>
            </tr>

            {{-- Kinderanzahl --}}
            <tr class="hp-row-parameter border-b border-gray-100">
                <td class="sticky left-0 bg-sky-50 border-r border-gray-200 px-3 py-2 font-medium text-gray-700 text-[11px]">
                    Kinderanzahl
                </td>
                @foreach($vergleichListe as $e)
                @php
                    $ka = $e['planung_a']['kinderanzahl'] ?? ($planung->monate->first(fn($m) => $m->monat->format('Y-m') === $e['monat'])?->kinderanzahl);
                    $kb = $e['planung_b']['kinderanzahl'] ?? ($other->monate->first(fn($m) => $m->monat->format('Y-m') === $e['monat'])?->kinderanzahl);
                    $kd = ($ka !== null && $kb !== null) ? ($ka - $kb) : null;
                @endphp
                <td class="px-2 py-2 text-center bg-blue-50/20">{{ $ka ?? '–' }}</td>
                <td class="px-2 py-2 text-center bg-emerald-50/20">{{ $kb ?? '–' }}</td>
                <td class="px-2 py-2 text-center bg-gray-100/60 border-r border-gray-200 font-semibold {{ $diffCss($kd) }}">
                    {{ $kd !== null ? $fmtSign($kd, 0) : '–' }}
                </td>
                @endforeach
            </tr>

            {{-- ── Summen ─────────────────────────────────────────────────── --}}
            <tr>
                <td colspan="{{ 1 + $vergleichListe->count() * 3 }}"
                    class="sticky left-0 bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Summen
                </td>
            </tr>

            {{-- Summe SP1 --}}
            <tr class="hp-row-summe border-b border-gray-100">
                <td class="sticky left-0 bg-blue-50 border-r border-gray-200 px-3 py-2 font-semibold text-gray-700 text-[11px]">
                    Summe Stunden (SP1)
                </td>
                @foreach($vergleichListe as $e)
                <td class="px-2 py-2 text-center text-blue-700 font-semibold bg-blue-50/40">
                    {{ $fmt($e['planung_a']['summe_sp1'] ?? null) }}
                </td>
                <td class="px-2 py-2 text-center text-emerald-700 font-semibold bg-emerald-50/40">
                    {{ $fmt($e['planung_b']['summe_sp1'] ?? null) }}
                </td>
                <td class="px-2 py-2 text-center bg-gray-100/60 border-r border-gray-200 font-semibold {{ $diffCss($e['diff_sp1'] ?? null) }}">
                    {{ $fmtSign($e['diff_sp1'] ?? null) }}
                </td>
                @endforeach
            </tr>

            {{-- Summe SP2 --}}
            <tr class="hp-row-summe border-b border-gray-100">
                <td class="sticky left-0 bg-blue-50 border-r border-gray-200 px-3 py-2 font-semibold text-gray-700 text-[11px]">
                    Summe Stunden (SP2)
                </td>
                @foreach($vergleichListe as $e)
                <td class="px-2 py-2 text-center text-blue-600 bg-blue-50/20">
                    {{ $fmt($e['planung_a']['summe_sp2'] ?? null) }}
                </td>
                <td class="px-2 py-2 text-center text-emerald-600 bg-emerald-50/20">
                    {{ $fmt($e['planung_b']['summe_sp2'] ?? null) }}
                </td>
                <td class="px-2 py-2 text-center bg-gray-100/60 border-r border-gray-200 font-semibold {{ $diffCss($e['diff_sp2'] ?? null) }}">
                    {{ $fmtSign($e['diff_sp2'] ?? null) }}
                </td>
                @endforeach
            </tr>

            {{-- VZÄ SP1 --}}
            <tr class="hp-row-summe border-b border-gray-300">
                <td class="sticky left-0 bg-blue-50 border-r border-gray-200 px-3 py-2 font-bold text-gray-700 text-[11px]">
                    VZÄ (SP1)
                </td>
                @foreach($vergleichListe as $e)
                <td class="px-2 py-2 text-center text-blue-800 font-bold bg-blue-50/60">
                    {{ $fmt($e['planung_a']['summe_vz_sp1'] ?? null, 3) }}
                </td>
                <td class="px-2 py-2 text-center text-emerald-800 font-bold bg-emerald-50/60">
                    {{ $fmt($e['planung_b']['summe_vz_sp1'] ?? null, 3) }}
                </td>
                <td class="px-2 py-2 text-center bg-gray-100/60 border-r border-gray-200 font-semibold {{ $diffCss($e['diff_vz'] ?? null) }}">
                    {{ $fmtSign($e['diff_vz'] ?? null, 3) }}
                </td>
                @endforeach
            </tr>

            {{-- ── Gesetzliche Schwellwerte ──────────────────────────────── --}}
            <tr>
                <td colspan="{{ 1 + $vergleichListe->count() * 3 }}"
                    class="sticky left-0 bg-amber-100 border-y border-amber-200 px-3 py-1 text-[10px] font-bold
                           text-amber-700 uppercase tracking-widest">
                    ⚖ Gesetzliche Schwellwerte
                </td>
            </tr>

            {{-- Summe gesetz. VZÄ --}}
            <tr class="hp-row-gesetzl border-b border-amber-100/60">
                <td class="sticky left-0 bg-amber-50 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                    Gesetzl. VZÄ-Minimum
                </td>
                @foreach($vergleichListe as $e)
                <td class="px-2 py-2 text-center text-amber-700 font-semibold bg-amber-50/30">
                    {{ $fmt($e['planung_a']['summe_gesetz_vz'] ?? null, 3) }}
                </td>
                <td class="px-2 py-2 text-center text-amber-700 font-semibold bg-amber-50/20">
                    {{ $fmt($e['planung_b']['summe_gesetz_vz'] ?? null, 3) }}
                </td>
                <td class="px-2 py-2 text-center bg-gray-100/60 border-r border-gray-200 font-semibold">
                    @php $dg = isset($e['planung_a']['summe_gesetz_vz'], $e['planung_b']['summe_gesetz_vz'])
                        ? round($e['planung_a']['summe_gesetz_vz'] - $e['planung_b']['summe_gesetz_vz'], 3)
                        : null; @endphp
                    <span class="{{ $diffCss($dg) }}">{{ $fmtSign($dg, 3) }}</span>
                </td>
                @endforeach
            </tr>

            {{-- ── Ergebnis ──────────────────────────────────────────────── --}}
            <tr>
                <td colspan="{{ 1 + $vergleichListe->count() * 3 }}"
                    class="sticky left-0 bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Ergebnis
                </td>
            </tr>

            {{-- Budget-Rest SP1 --}}
            <tr class="border-b-2 border-gray-300">
                <td class="sticky left-0 bg-gray-100 border-r border-gray-200 px-3 py-2 font-bold text-gray-800 text-[11px]">
                    📊 Budget-Rest (SP1)
                </td>
                @foreach($vergleichListe as $e)
                @php
                    $brA = $e['planung_a']['budget_rest_sp1'] ?? null;
                    $brB = $e['planung_b']['budget_rest_sp1'] ?? null;
                @endphp
                <td class="px-2 py-2.5 text-center font-bold
                           {{ $brA === null ? 'text-gray-400' : ($brA >= 0 ? 'budget-positiv' : 'budget-negativ') }}">
                    {{ $brA !== null ? $fmtSign($brA) : '–' }}
                </td>
                <td class="px-2 py-2.5 text-center font-bold
                           {{ $brB === null ? 'text-gray-400' : ($brB >= 0 ? 'budget-positiv' : 'budget-negativ') }}">
                    {{ $brB !== null ? $fmtSign($brB) : '–' }}
                </td>
                <td class="px-2 py-2.5 text-center bg-gray-100/60 border-r border-gray-200 font-semibold {{ $diffCss($e['diff_gesetzl'] ?? null) }}">
                    {{ $fmtSign($e['diff_gesetzl'] ?? null) }}
                </td>
                @endforeach
            </tr>

            {{-- Diff. VZÄ SP2 --}}
            <tr class="hp-row-ergebnis border-b border-gray-200">
                <td class="sticky left-0 bg-gray-50 border-r border-gray-200 px-3 py-2 text-gray-700 font-medium text-[11px]">
                    Diff. VZÄ Stadt (SP2)
                </td>
                @foreach($vergleichListe as $e)
                @php
                    $dvA = $e['planung_a']['differenz_vz_sp2'] ?? null;
                    $dvB = $e['planung_b']['differenz_vz_sp2'] ?? null;
                @endphp
                <td class="px-2 py-2 text-center font-semibold {{ $diffCss($dvA) }}">{{ $fmtSign($dvA, 3) }}</td>
                <td class="px-2 py-2 text-center font-semibold {{ $diffCss($dvB) }}">{{ $fmtSign($dvB, 3) }}</td>
                <td class="px-2 py-2 text-center bg-gray-100/60 border-r border-gray-200 text-gray-400">–</td>
                @endforeach
            </tr>

            {{-- ── Personen (wenn vorhanden) ──────────────────────────────── --}}
            @if($personenA->count() > 0)
            <tr>
                <td colspan="{{ 1 + $vergleichListe->count() * 3 }}"
                    class="sticky left-0 bg-gray-100 border-y border-gray-300 px-3 py-1 text-[10px] font-bold
                           text-gray-600 uppercase tracking-widest">
                    Personal (SP1)
                </td>
            </tr>

            @foreach($personenA as $uid => $name)
            <tr class="border-b border-gray-100 hover:bg-blue-50/20">
                <td class="sticky left-0 bg-white border-r border-gray-200 px-3 py-2 font-medium text-gray-800 text-[11px]">
                    {{ $name }}
                </td>
                @foreach($vergleichListe as $e)
                @php
                    $mk   = $e['monat'];
                    $pA   = $pDataA[$mk][$uid] ?? null;
                    $pB   = $pDataB[$mk][$uid] ?? null;
                    $sA   = $pA?->stunden_gesamt;
                    $sB   = $pB?->stunden_gesamt;
                    $diff = ($sA !== null && $sB !== null) ? round($sA - $sB, 2) : null;
                @endphp
                <td class="px-2 py-2 text-center bg-blue-50/20 text-blue-700">
                    {{ $sA !== null ? $fmt($sA, 1) : '–' }}
                </td>
                <td class="px-2 py-2 text-center bg-emerald-50/20 text-emerald-700">
                    {{ $sB !== null ? $fmt($sB, 1) : '–' }}
                </td>
                <td class="px-2 py-2 text-center bg-gray-100/60 border-r border-gray-200 font-semibold {{ $diffCss($diff) }}">
                    {{ $diff !== null ? $fmtSign($diff, 1) : '–' }}
                </td>
                @endforeach
            </tr>
            @endforeach
            @endif

            </tbody>
        </table>
        </div>

        {{-- Legende --}}
        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/80 flex flex-wrap gap-x-5 gap-y-1 text-[10px] text-gray-500">
            <span><span class="hp-diff-pos">+Wert</span> = Plan A ist höher als Plan B</span>
            <span><span class="hp-diff-neg">−Wert</span> = Plan A ist niedriger als Plan B</span>
            <span>Budget-Rest: positiv = mehr Spielraum · negativ = Unterdeckung</span>
        </div>
    </div>

    {{-- ── Trend-Chart: beide Planungen ───────────────────────────────────── --}}
    @if(count($chartLabels) > 0)
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-6">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                </svg>
                VZÄ-Verlauf im Vergleich
            </h2>
            <div class="hidden sm:flex items-center gap-4 text-[11px] text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-5 h-0.5 bg-blue-500 rounded"></span>
                    VZÄ Plan A (SP1)
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-5 h-0.5 bg-emerald-500 rounded"></span>
                    VZÄ Plan B (SP1)
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-5 border-t-2 border-dashed border-red-400 rounded"></span>
                    Gesetzl. Minimum (A)
                </span>
            </div>
        </div>
        <div class="p-4" style="position: relative; height: 280px;">
            <canvas id="vergleichChart" aria-label="VZÄ-Vergleichs-Chart" role="img"></canvas>
        </div>
    </div>
    @endif

    @endif {{-- Ende if $vergleichListe->isNotEmpty() --}}

</div>
</div>
@endsection

@push('js')
@if(count($chartLabels ?? []) > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    const ctx = document.getElementById('vergleichChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($chartLabels),
            datasets: [
                {
                    label: 'VZÄ Plan A (SP1)',
                    data: @json($chartVzA),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.08)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: false,
                    tension: 0.3,
                },
                {
                    label: 'VZÄ Plan B (SP1)',
                    data: @json($chartVzB),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: false,
                    tension: 0.3,
                },
                {
                    label: 'Gesetzl. Minimum Plan A',
                    data: @json($chartGesetzlA),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.5,
                    borderDash: [5, 4],
                    pointRadius: 0,
                    fill: false,
                },
                {
                    label: 'Gesetzl. Minimum Plan B',
                    data: @json($chartGesetzlB),
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.5,
                    borderDash: [3, 4],
                    pointRadius: 0,
                    fill: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 }, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const v = ctx.raw;
                            if (v === null || v === undefined) return null;
                            return ctx.dataset.label + ': ' + new Intl.NumberFormat('de-DE', { minimumFractionDigits: 3 }).format(v);
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { font: { size: 10 }, maxRotation: 45 }, grid: { display: false } },
                y: { ticks: { font: { size: 10 } }, title: { display: true, text: 'VZÄ', font: { size: 11 } } },
            },
        }
    });
})();
</script>
@endif
@endpush

