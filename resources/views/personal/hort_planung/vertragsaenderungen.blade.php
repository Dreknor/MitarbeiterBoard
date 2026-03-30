@extends('layouts.app')

@section('title', 'Vertragsänderungen – ' . $planung->name)
@section('site-title', 'Hortstunden-Planung')

@push('css')
    @vite(['resources/css/hort-planung.css'])
@endpush

@section('content')
<div class="hort-planung-wrapper">
<div class="max-w-full px-4 py-6">

@php
    $fmt = fn(?float $v, int $d = 2): string =>
        $v !== null ? number_format($v, $d, ',', '.') : '–';

    $fmtSign = fn(?float $v, int $d = 2): string =>
        $v !== null ? (($v > 0 ? '+' : '') . number_format($v, $d, ',', '.')) : '–';

    $anzahlAenderungen = $aenderungen->flatten(1)->count();
    $anzahlPersonen    = $aenderungen->count();
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
        <span class="text-gray-800 font-medium">Vertragsänderungen</span>
    </nav>

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span class="text-2xl">📋</span>
                Zu ändernde Verträge
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                <span class="font-medium text-gray-700">{{ $planung->name }}</span>
                &middot; {{ $planung->department?->name ?? '–' }}
                &middot; {{ $anzahlPersonen }} {{ $anzahlPersonen === 1 ? 'Person' : 'Personen' }}
                &middot; {{ $anzahlAenderungen }} {{ $anzahlAenderungen === 1 ? 'Änderung' : 'Änderungen' }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
            {{-- Excel-Export --}}
            <a href="{{ route('hort-planung.exportVertragsaenderungen', $planung) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-emerald-50 text-emerald-700
                      text-sm font-medium rounded-xl border border-emerald-200 shadow-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Excel-Export
            </a>

            {{-- Zurück zur Planung --}}
            <a href="{{ route('hort-planung.show', $planung) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700
                      text-sm font-medium rounded-xl border border-gray-200 shadow-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Zur Planung
            </a>
        </div>
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

    {{-- ── Legende ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-gray-500 mb-3 px-1">
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-blue-100 border border-blue-300"></span>
            <strong class="text-gray-600">SP1</strong> – geplante Stunden (Verein)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-slate-100 border border-slate-300"></span>
            <strong class="text-gray-600">SP2</strong> – Stadtstunden (Abrechnung)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-amber-100 border border-amber-300"></span>
            <strong class="text-gray-600">Zusatz</strong> – Anteil Zusatzstunden pro Person
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-emerald-100 border border-emerald-300"></span>
            <strong class="text-gray-600">Gesamt SP1</strong> – SP1 + Zusatzstunden-Anteil
        </span>
    </div>

    {{-- ── Leerzustand ─────────────────────────────────────────────────────── --}}
    @if($aenderungen->isEmpty())
    <div class="text-center py-16 bg-white rounded-2xl border border-emerald-200 shadow-sm">
        <svg class="w-12 h-12 mx-auto mb-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm font-medium text-emerald-700">
            Keine Vertragsänderungen nötig.
        </p>
        <p class="text-xs text-gray-400 mt-1">
            Alle SP1- und SP2-Werte sind über den gesamten Planungszeitraum konstant.
        </p>
    </div>
    @else

    {{-- ── Tabelle pro Person ──────────────────────────────────────────────── --}}
    @foreach($aenderungen as $userId => $personAenderungen)
    @php
        $firstEntry = $personAenderungen->first();
        $personName = $firstEntry['user_name'] ?? '–';
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
        {{-- Personen-Header --}}
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-base">👤</span>
                <h3 class="text-sm font-semibold text-gray-800">{{ $personName }}</h3>
                <span class="text-xs text-gray-400">
                    {{ $personAenderungen->count() }} {{ $personAenderungen->count() === 1 ? 'Änderung' : 'Änderungen' }}
                </span>
            </div>
        </div>

        {{-- Tabelle --}}
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-200 bg-gray-50 text-gray-500">
                        <th class="px-4 py-2.5 text-left font-semibold">Ab Monat</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-blue-600">SP1 vorher</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-blue-700">SP1 neu</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-slate-600">SP2 vorher</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-slate-700">SP2 neu</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-amber-600">Zusatzstd.</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-emerald-700">Gesamt SP1</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-gray-600">Vertrag</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-amber-700">Δ SP1–Vertrag</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($personAenderungen as $idx => $a)
                    @php
                        $istVergangen = $a['monat']->lessThan(now()->startOfMonth());
                        $hatVertragAbweichung = $a['vertrag'] !== null && abs($a['differenz']) >= 0.01;
                    @endphp
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}
                               {{ $istVergangen ? 'opacity-60' : '' }}
                               hover:bg-blue-50/30 border-b border-gray-100">
                        {{-- Ab Monat --}}
                        <td class="px-4 py-2.5 font-medium text-gray-800">
                            {{ $a['monat_label'] }}
                            @if($istVergangen)
                                <span class="ml-1 text-[10px] text-gray-400">(vergangen)</span>
                            @endif
                            @if($a['sp1_geaendert'] && $a['sp2_geaendert'])
                                <span class="ml-1 text-[10px] bg-blue-100 text-blue-600 px-1 py-0.5 rounded">SP1+SP2</span>
                            @elseif($a['sp1_geaendert'])
                                <span class="ml-1 text-[10px] bg-blue-100 text-blue-600 px-1 py-0.5 rounded">SP1</span>
                            @elseif($a['sp2_geaendert'])
                                <span class="ml-1 text-[10px] bg-slate-100 text-slate-600 px-1 py-0.5 rounded">SP2</span>
                            @endif
                        </td>

                        {{-- SP1 vorher --}}
                        <td class="px-3 py-2.5 text-right text-gray-400">
                            {{ $a['sp1_vorher'] !== null ? $fmt($a['sp1_vorher'], 1) : '–' }}
                        </td>

                        {{-- SP1 neu --}}
                        <td class="px-3 py-2.5 text-right font-semibold {{ $a['sp1_geaendert'] ? 'text-blue-700' : 'text-gray-500' }}">
                            {{ $a['sp1'] !== null ? $fmt($a['sp1'], 1) : '–' }}
                            @if($a['sp1_geaendert'] && $a['sp1_vorher'] !== null && $a['sp1'] !== null)
                                @php $diff = $a['sp1'] - $a['sp1_vorher']; @endphp
                                <br>
                                <span class="text-[10px] font-normal {{ $diff > 0 ? 'text-blue-400' : 'text-red-400' }}">
                                    {{ $fmtSign($diff, 1) }}
                                </span>
                            @endif
                        </td>

                        {{-- SP2 vorher --}}
                        <td class="px-3 py-2.5 text-right text-gray-400">
                            {{ $a['sp2_vorher'] !== null ? $fmt($a['sp2_vorher'], 1) : '–' }}
                        </td>

                        {{-- SP2 neu --}}
                        <td class="px-3 py-2.5 text-right font-semibold {{ $a['sp2_geaendert'] ? 'text-slate-700' : 'text-gray-500' }}">
                            {{ $a['sp2'] !== null ? $fmt($a['sp2'], 1) : '–' }}
                            @if($a['sp2_geaendert'] && $a['sp2_vorher'] !== null && $a['sp2'] !== null)
                                @php $diff = $a['sp2'] - $a['sp2_vorher']; @endphp
                                <br>
                                <span class="text-[10px] font-normal {{ $diff > 0 ? 'text-slate-400' : 'text-red-400' }}">
                                    {{ $fmtSign($diff, 1) }}
                                </span>
                            @endif
                        </td>

                        {{-- Zusatzstunden --}}
                        <td class="px-3 py-2.5 text-right text-amber-600">
                            {{ $fmt($a['zusatzstunden'], 1) }}
                        </td>

                        {{-- Gesamtwert SP1 --}}
                        <td class="px-3 py-2.5 text-right font-semibold text-emerald-700">
                            {{ $fmt($a['gesamtwert_sp1'], 1) }}
                        </td>

                        {{-- Vertrag --}}
                        <td class="px-3 py-2.5 text-right text-gray-600">
                            {{ $a['vertrag'] !== null ? $fmt($a['vertrag'], 1) : '–' }}
                        </td>

                        {{-- Differenz SP1–Vertrag --}}
                        <td class="px-3 py-2.5 text-right font-semibold
                                   {{ $hatVertragAbweichung ? 'text-amber-700 bg-amber-50' : 'text-gray-400' }}">
                            @if($a['vertrag'] !== null)
                                {{ $fmtSign($a['differenz'], 1) }}
                                @if($hatVertragAbweichung)
                                    <span class="ml-0.5 text-amber-500">⚠</span>
                                @endif
                            @else
                                –
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    {{-- ── Zusammenfassung ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                <span class="text-base">📊</span>
                Zusammenfassung
            </h3>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-xl border border-blue-100">
                    <div class="text-2xl font-bold text-blue-700">{{ $anzahlPersonen }}</div>
                    <div class="text-xs text-blue-600 mt-1">{{ $anzahlPersonen === 1 ? 'Person betroffen' : 'Personen betroffen' }}</div>
                </div>
                <div class="text-center p-4 bg-amber-50 rounded-xl border border-amber-100">
                    <div class="text-2xl font-bold text-amber-700">{{ $anzahlAenderungen }}</div>
                    <div class="text-xs text-amber-600 mt-1">{{ $anzahlAenderungen === 1 ? 'Vertragsänderung' : 'Vertragsänderungen' }} gesamt</div>
                </div>
                <div class="text-center p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                    @php
                        $abweichungen = $aenderungen->flatten(1)->filter(fn($a) => $a['vertrag'] !== null && abs($a['differenz']) >= 0.01)->count();
                    @endphp
                    <div class="text-2xl font-bold text-emerald-700">{{ $abweichungen }}</div>
                    <div class="text-xs text-emerald-600 mt-1">davon mit Vertrag-Abweichung</div>
                </div>
            </div>
        </div>
    </div>

    @endif

</div>
</div>
@endsection

