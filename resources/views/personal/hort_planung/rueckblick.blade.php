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
    // Der Rückblick zeigt ausschließlich die Planung (SP1/SP2) vergangener
    // Monate – bewusst ohne Abgleich mit Stundenzetteln.

    $allePersonen = $monate
        ->flatMap(fn($m) => $m->personen)
        ->unique('user_id')
        ->sortBy(fn($p) => $p->user?->name ?? 'zzz')
        ->values();

    $personenNachMonat = $monate->mapWithKeys(fn($m) => [
        $m->monat->format('Y-m') => $m->personen->keyBy('user_id'),
    ]);

    $monatsListe = $monate->sortBy('monat')->values();

    // ── Chart-Daten vorbereiten ────────────────────────────────────────────────
    $chartLabels    = [];
    $chartVzSp1     = [];
    $chartVzGesetzl = [];
    $chartBudget    = [];
    $chartVzIst     = [];

    foreach ($monatsListe as $m) {
        $ber = $berechnungenNachMonat->get($m->monat->format('Y-m')) ?? [];

        $chartLabels[]    = $m->monat->locale('de')->isoFormat('MMM YY');
        $chartVzSp1[]     = isset($ber['summe_vz_sp1'])    ? round($ber['summe_vz_sp1'], 3)    : null;
        $chartVzGesetzl[] = isset($ber['summe_gesetz_vz']) ? round($ber['summe_gesetz_vz'], 3) : null;
        $chartBudget[]    = isset($ber['budget_rest_sp1']) ? round($ber['budget_rest_sp1'], 2) : null;
        $chartVzIst[]     = null;
    }

    $fmt = fn(?float $v, int $d = 1): string =>
        $v !== null ? number_format($v, $d, ',', '.') : '–';

    $fmtSign = fn(?float $v, int $d = 1): string =>
        $v !== null ? (($v > 0 ? '+' : '') . number_format($v, $d, ',', '.')) : '–';

    $colspan = 1 + $monatsListe->count() * 2;
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
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-800">Rückblick: geplante Stunden</h1>
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
            <strong class="text-blue-700">SP1</strong> – Vereinsstunden (Soll gesamt)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-slate-100 border border-slate-300"></span>
            <strong class="text-slate-600">SP2</strong> – Stadtstunden (Abrechnung)
        </span>
    </div>

    {{-- ── Haupt-Tabelle ────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse" style="min-width: {{ 220 + $monatsListe->count() * 130 }}px">

                <thead>
                    <tr class="border-b border-gray-200">
                        <th scope="col"
                            class="sticky left-0 z-20 bg-gray-50 border-r border-gray-200 w-52 min-w-52 px-3 py-2 text-left font-semibold text-gray-600 uppercase tracking-wide text-[10px]">
                            Person / Kennzahl
                        </th>
                        @foreach($monatsListe as $monat)
                        <th colspan="2"
                            class="px-2 py-2 text-center font-semibold text-gray-700 border-r border-gray-200 bg-gray-50">
                            {{ $monat->monat->locale('de')->isoFormat('MMMM YYYY') }}
                            @if($monat->notiz)
                                <span class="ml-1 text-amber-500 cursor-help" title="{{ $monat->notiz }}">ℹ</span>
                            @endif
                        </th>
                        @endforeach
                    </tr>
                    <tr class="border-b-2 border-gray-200 bg-gray-50">
                        <th class="sticky left-0 z-20 bg-gray-50 border-r border-gray-200 px-3 py-1.5"></th>
                        @foreach($monatsListe as $monat)
                        <th class="px-2 py-1.5 text-center font-medium text-blue-700 bg-blue-50/50 w-16 min-w-16">SP1</th>
                        <th class="px-2 py-1.5 text-center font-medium text-slate-600 bg-slate-50/50 border-r border-gray-200 w-16 min-w-16">SP2</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse($allePersonen as $idx => $person)
                    @php
                        $userId   = $person->user_id;
                        $userName = $person->user?->name ?? 'Unbekannt (#' . $userId . ')';
                        $rowBg    = $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                    @endphp
                    <tr class="{{ $rowBg }} hover:bg-blue-50/30 border-b border-gray-100">
                        <td class="sticky left-0 z-10 border-r border-gray-200 px-3 py-2 font-medium text-gray-800 {{ $rowBg }}">
                            {{ $userName }}
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $eintrag = $personenNachMonat->get($monat->monat->format('Y-m'))?->get($userId);
                        @endphp
                        <td class="px-2 py-2 text-center text-gray-700">{{ $fmt($eintrag?->stunden_gesamt) }}</td>
                        <td class="px-2 py-2 text-center text-gray-500 border-r border-gray-200">{{ $fmt($eintrag?->stunden_stadt) }}</td>
                        @endforeach
                    </tr>
                    @empty
                    <tr>
                        <td class="sticky left-0 z-10 bg-white border-r border-gray-200 px-3 py-4 text-gray-400 italic"
                            colspan="{{ $colspan }}">
                            Noch keine Personen in dieser Planung vorhanden.
                        </td>
                    </tr>
                    @endforelse

                    {{-- ── Summen / Ergebnis ─────────────────────────────────── --}}
                    @php
                        $zeilen = [
                            ['label' => 'Summe Stunden',          'sp1' => 'summe_sp1',              'sp2' => 'summe_sp2',       'd' => 1, 'sign' => false, 'css' => 'bg-gray-100 font-semibold border-t-2 border-gray-300'],
                            ['label' => 'Summe VZÄ',              'sp1' => 'summe_vz_sp1',           'sp2' => 'summe_vz_sp2',    'd' => 3, 'sign' => false, 'css' => 'bg-white'],
                            ['label' => '⚖ Gesetzl. Minimum (h)', 'sp1' => 'summe_stunden_gesetzl',  'sp2' => null,              'd' => 1, 'sign' => false, 'css' => 'bg-amber-50/40'],
                            ['label' => 'Budget gesamt (h)',      'sp1' => 'budget_gesamt',          'sp2' => null,              'd' => 1, 'sign' => false, 'css' => 'bg-white'],
                            ['label' => 'Budget-Rest SP1 / Diff. Stadt SP2', 'sp1' => 'budget_rest_sp1', 'sp2' => 'differenz_stadt', 'd' => 1, 'sign' => true, 'css' => 'bg-gray-50 font-semibold'],
                        ];
                    @endphp
                    @foreach($zeilen as $z)
                    <tr class="{{ $z['css'] }} border-b border-gray-200">
                        <td class="sticky left-0 z-10 border-r border-gray-200 px-3 py-2 text-gray-700 text-[11px] {{ str_contains($z['css'], 'bg-white') ? 'bg-white' : 'bg-gray-50' }}">
                            {{ $z['label'] }}
                        </td>
                        @foreach($monatsListe as $monat)
                        @php
                            $ber = $berechnungenNachMonat->get($monat->monat->format('Y-m')) ?? [];
                            $v1  = $z['sp1'] ? ($ber[$z['sp1']] ?? null) : null;
                            $v2  = $z['sp2'] ? ($ber[$z['sp2']] ?? null) : null;
                            $c1  = $z['sign'] && $v1 !== null ? ($v1 >= 0 ? 'budget-positiv' : 'budget-negativ') : 'text-blue-700';
                            $c2  = $z['sign'] && $v2 !== null ? ($v2 >= 0 ? 'sp2-gedeckt' : 'sp2-unterdeckung') : 'text-slate-600';
                        @endphp
                        <td class="px-2 py-2 text-center {{ $c1 }}">{{ $z['sign'] ? $fmtSign($v1, $z['d']) : $fmt($v1, $z['d']) }}</td>
                        <td class="px-2 py-2 text-center border-r border-gray-200 {{ $c2 }}">
                            @if($z['sp2']){{ $z['sign'] ? $fmtSign($v2, $z['d']) : $fmt($v2, $z['d']) }}@endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/80 flex flex-wrap gap-x-5 gap-y-1 text-[10px] text-gray-500">
            <span><strong>Budget-Rest SP1</strong> = Budget gesamt − Σ SP1 (<span class="budget-negativ">negativ</span> = Überplanung)</span>
            <span><strong>Diff. Stadt SP2</strong> = Σ SP2 − gesetzl. Minimum (<span class="sp2-unterdeckung">negativ</span> = Unterdeckung)</span>
        </div>
    </div>

    {{-- ── VZÄ-Trend-Chart ─────────────────────────────────────────────────── --}}
    @include('personal.hort_planung._trend_chart', [
        'chartLabels'    => $chartLabels,
        'chartVzSp1'     => $chartVzSp1,
        'chartVzGesetzl' => $chartVzGesetzl,
        'chartBudget'    => $chartBudget,
        'chartVzIst'     => $chartVzIst,
        'chartTitel'     => 'VZÄ-Verlauf – Planung vs. Gesetzliches Minimum',
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
