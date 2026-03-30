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
                   (session('type') === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' :
                   (session('type') === 'danger'  ? 'bg-red-50 text-red-800 border border-red-200' :
                    'bg-blue-50 text-blue-800 border border-blue-200')) }}"
         role="alert">
        {{ session('Meldung') }}
    </div>
    @endif

    @if($errors->any())
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium bg-red-50 text-red-800 border border-red-200" role="alert">
        @foreach($errors->all() as $error)
            {{ $error }}<br>
        @endforeach
    </div>
    @endif

    {{-- ── Erklärung ───────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-gray-500 mb-3 px-1">
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-slate-100 border border-slate-300"></span>
            <strong class="text-gray-600">Stundenhort (SP2)</strong> – Stadtstunden
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-amber-100 border border-amber-300"></span>
            <strong class="text-gray-600">Zusatzstunden</strong> – SP1 − SP2
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-blue-100 border border-blue-300"></span>
            <strong class="text-gray-600">Gesamt SP1</strong> – Stundenhort + Zusatzstunden
        </span>
        <span class="text-gray-400">·</span>
        <span>Aufgelistet wird jeder Monat, in dem sich SP1 oder SP2 gegenüber dem Vormonat ändert.</span>
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
                        <th class="px-3 py-2.5 text-center font-semibold">Änderung</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-slate-600">Stundenhort (SP2)</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-amber-600">Zusatzstunden</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-blue-700">Gesamt SP1</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-gray-500">Vertrag</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($personAenderungen as $idx => $a)
                    @php
                        $istVergangen = $a['monat']->lessThan(now()->startOfMonth());
                        $sp1  = $a['sp1'];
                        $sp2  = $a['sp2'];
                        $zusatz = $a['zusatzstunden']; // = SP1 − SP2
                    @endphp
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}
                               {{ $istVergangen ? 'opacity-50' : '' }}
                               hover:bg-blue-50/30 border-b border-gray-100">

                        {{-- Ab Monat --}}
                        <td class="px-4 py-2.5 font-medium text-gray-800 whitespace-nowrap">
                            {{ $a['monat_label'] }}
                            @if($istVergangen)
                                <span class="ml-1 text-[10px] text-gray-400">(vergangen)</span>
                            @endif
                        </td>

                        {{-- Was hat sich geändert? --}}
                        <td class="px-3 py-2.5 text-center whitespace-nowrap">
                            @if($a['sp1_geaendert'] && $a['sp2_geaendert'])
                                <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-medium">SP1+SP2</span>
                            @elseif($a['sp1_geaendert'])
                                <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-medium">SP1</span>
                            @elseif($a['sp2_geaendert'])
                                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-medium">SP2</span>
                            @endif

                            {{-- Kompakte Änderungsanzeige --}}
                            <div class="mt-0.5 text-[10px] text-gray-400 leading-tight">
                                @if($a['sp1_geaendert'])
                                    SP1: {{ $a['sp1_vorher'] !== null ? $fmt($a['sp1_vorher'], 1) : '–' }} → {{ $fmt($sp1, 1) }}
                                    @if($a['sp1_vorher'] !== null && $sp1 !== null)
                                        <span class="{{ ($sp1 - $a['sp1_vorher']) > 0 ? 'text-blue-500' : 'text-red-400' }}">
                                            ({{ $fmtSign($sp1 - $a['sp1_vorher'], 1) }})
                                        </span>
                                    @endif
                                    <br>
                                @endif
                                @if($a['sp2_geaendert'])
                                    SP2: {{ $a['sp2_vorher'] !== null ? $fmt($a['sp2_vorher'], 1) : '–' }} → {{ $fmt($sp2, 1) }}
                                    @if($a['sp2_vorher'] !== null && $sp2 !== null)
                                        <span class="{{ ($sp2 - $a['sp2_vorher']) > 0 ? 'text-slate-500' : 'text-red-400' }}">
                                            ({{ $fmtSign($sp2 - $a['sp2_vorher'], 1) }})
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>

                        {{-- Stundenhort (SP2) --}}
                        <td class="px-3 py-2.5 text-right text-slate-700 font-medium">
                            {{ $sp2 !== null ? $fmt($sp2, 1) : '–' }}
                        </td>

                        {{-- Zusatzstunden = SP1 − SP2 --}}
                        <td class="px-3 py-2.5 text-right font-semibold {{ $zusatz > 0.01 ? 'text-amber-600' : 'text-gray-400' }}">
                            @if($zusatz > 0.01)
                                +{{ $fmt($zusatz, 1) }}
                            @elseif($zusatz < -0.01)
                                {{ $fmt($zusatz, 1) }}
                            @else
                                –
                            @endif
                        </td>

                        {{-- Gesamt SP1 = SP2 + Zusatzstunden --}}
                        <td class="px-3 py-2.5 text-right font-bold text-blue-700">
                            {{ $sp1 !== null ? $fmt($sp1, 1) : '–' }}
                        </td>

                        {{-- Vertrag --}}
                        <td class="px-3 py-2.5 text-right text-gray-500">
                            @if($a['vertrag'] !== null)
                                {{ $fmt($a['vertrag'], 1) }}
                                @php $vertragDiff = ($sp1 ?? 0) - $a['vertrag']; @endphp
                                @if(abs($vertragDiff) >= 0.01)
                                    <br>
                                    <span class="text-[10px] text-amber-600 font-medium">
                                        Δ {{ $fmtSign($vertragDiff, 1) }} ⚠
                                    </span>
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
                        $nurSp1 = $aenderungen->flatten(1)->filter(fn($a) => $a['sp1_geaendert'] && !$a['sp2_geaendert'])->count();
                        $nurSp2 = $aenderungen->flatten(1)->filter(fn($a) => !$a['sp1_geaendert'] && $a['sp2_geaendert'])->count();
                        $beide  = $aenderungen->flatten(1)->filter(fn($a) => $a['sp1_geaendert'] && $a['sp2_geaendert'])->count();
                    @endphp
                    <div class="text-sm font-bold text-emerald-700">
                        <span class="text-blue-600">{{ $nurSp1 }}× SP1</span>
                        · <span class="text-slate-600">{{ $nurSp2 }}× SP2</span>
                        · <span class="text-purple-600">{{ $beide }}× beide</span>
                    </div>
                    <div class="text-xs text-emerald-600 mt-1">Aufschlüsselung nach Änderungstyp</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Anstellungen erstellen ──────────────────────────────────────────── --}}
    @can('manage hort planung')
    <div class="bg-white rounded-2xl border border-orange-200 shadow-sm overflow-hidden mt-6"
         x-data="{ showVorschau: false }">
        <div class="px-5 py-3.5 border-b border-orange-100 bg-orange-50">
            <h3 class="text-sm font-semibold text-orange-800 flex items-center gap-2">
                <span class="text-base">⚙️</span>
                Befristete Anstellungen erstellen
            </h3>
            <p class="text-xs text-orange-600 mt-1">
                Prüft pro Änderungsmonat die Summe <strong>aller aktiven Anstellungen</strong> (befristet + unbefristet).
                Nur wenn SP1 davon abweicht, werden befristete Anstellungen erstellt:
                <strong>SP2-Anpassung</strong> (Differenz bestehend → SP2) +
                <strong>Zusatzstunden</strong> (SP1 − SP2).
                Bestehende Anstellungen bleiben unverändert.
            </p>
        </div>

        <div class="p-5">
            {{-- Vorschau-Button --}}
            <button @click="showVorschau = !showVorschau" type="button"
                    class="text-xs text-orange-600 hover:text-orange-800 underline mb-4">
                <span x-text="showVorschau ? '▼ Vorschau ausblenden' : '▶ Vorschau der zu erstellenden Anstellungen anzeigen'"></span>
            </button>

            {{-- Vorschau-Tabelle --}}
            <div x-show="showVorschau" x-cloak class="mb-5">
                @php
                    $vorschauDaten = [];
                    foreach ($aenderungen as $userId => $personAenderungen) {
                        $liste = $personAenderungen->values()->all();

                        foreach ($liste as $idx => $a) {
                            $monatStart = $a['monat'];
                            $sp1 = (float) ($a['sp1'] ?? 0);
                            $sp2 = (float) ($a['sp2'] ?? 0);
                            $zusatz = round($sp1 - $sp2, 2);

                            $naechster = $liste[$idx + 1] ?? null;
                            $bisLabel = $naechster
                                ? $naechster['monat']->copy()->subDay()->format('d.m.Y')
                                : '(befristet bis)';

                            // Summe aller aktiven Anstellungen im Bereich zu diesem Monat
                            $existierend = (float) \App\Models\personal\Employment
                                ::where('employe_id', $userId)
                                ->where('department_id', $planung->department_id)
                                ->where('start', '<=', $monatStart)
                                ->where(function ($q) use ($monatStart) {
                                    $q->whereNull('end')->orWhere('end', '>=', $monatStart);
                                })
                                ->sum('hours');

                            $gedeckt  = abs($sp1 - $existierend) < 0.01;
                            $sp2Diff  = round($sp2 - $existierend, 2);

                            $vorschauDaten[] = [
                                'person'      => $a['user_name'],
                                'ab'          => $monatStart->format('d.m.Y'),
                                'bis'         => $bisLabel,
                                'existierend' => $existierend,
                                'sp2'         => $sp2,
                                'sp2_diff'    => $sp2Diff,
                                'zusatz'      => $zusatz,
                                'sp1'         => $sp1,
                                'gedeckt'     => $gedeckt,
                            ];
                        }
                    }
                @endphp
                <div class="overflow-x-auto border border-orange-100 rounded-xl">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-orange-50 border-b border-orange-200 text-orange-700">
                                <th class="px-3 py-2 text-left font-semibold">Person</th>
                                <th class="px-3 py-2 text-left font-semibold">Ab</th>
                                <th class="px-3 py-2 text-left font-semibold">Bis</th>
                                <th class="px-3 py-2 text-right font-semibold">Bestehend</th>
                                <th class="px-3 py-2 text-right font-semibold">Ziel SP1</th>
                                <th class="px-3 py-2 text-right font-semibold">→ Anst. SP2-Anp.</th>
                                <th class="px-3 py-2 text-right font-semibold">→ Anst. Zusatzstd.</th>
                                <th class="px-3 py-2 text-center font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vorschauDaten as $v)
                            <tr class="border-b border-gray-100 {{ $v['gedeckt'] ? 'opacity-40' : '' }}">
                                <td class="px-3 py-2 font-medium text-gray-800">{{ $v['person'] }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $v['ab'] }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $v['bis'] }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">
                                    {{ $v['existierend'] > 0 ? $fmt($v['existierend'], 1) . ' h' : '–' }}
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-blue-700">{{ $fmt($v['sp1'], 1) }} h</td>
                                <td class="px-3 py-2 text-right font-medium {{ !$v['gedeckt'] && abs($v['sp2_diff']) >= 0.01 ? 'text-orange-700' : 'text-gray-400' }}">
                                    @if($v['gedeckt'])
                                        –
                                    @elseif(abs($v['sp2_diff']) >= 0.01)
                                        {{ $v['sp2_diff'] > 0 ? '+' : '' }}{{ $fmt($v['sp2_diff'], 1) }} h
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right font-medium {{ !$v['gedeckt'] && abs($v['zusatz']) >= 0.01 ? 'text-amber-600' : 'text-gray-400' }}">
                                    @if($v['gedeckt'])
                                        –
                                    @elseif(abs($v['zusatz']) >= 0.01)
                                        {{ $v['zusatz'] > 0 ? '+' : '' }}{{ $fmt($v['zusatz'], 1) }} h
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($v['gedeckt'])
                                        <span class="text-emerald-600 text-[10px] font-medium">✓ gedeckt</span>
                                    @else
                                        <span class="text-orange-600 text-[10px] font-medium">neu</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @php $anzahlGedeckt = collect($vorschauDaten)->where('gedeckt', true)->count(); @endphp
                @if($anzahlGedeckt > 0)
                <p class="mt-2 text-[11px] text-emerald-600">
                    ✓ {{ $anzahlGedeckt }} Änderung(en) bereits durch bestehende Anstellungen gedeckt – keine neuen Einträge nötig.
                </p>
                @endif
            </div>

            {{-- Formular --}}
            <form method="POST" action="{{ route('hort-planung.applyVertragsaenderungen', $planung) }}"
                  onsubmit="return confirm('Es werden befristete Anstellungen für {{ $anzahlPersonen }} Person(en) erstellt. Fortfahren?')">
                @csrf
                <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <div>
                        <label for="befristet_bis" class="block text-xs font-medium text-gray-700 mb-1">
                            Befristet bis
                        </label>
                        <input type="date" name="befristet_bis" id="befristet_bis"
                               value="{{ old('befristet_bis') }}"
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               class="block w-full sm:w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg
                                      focus:ring-2 focus:ring-orange-300 focus:border-orange-400"
                               required>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-600 hover:bg-orange-700
                                   text-white text-sm font-medium rounded-xl shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 4v16m8-8H4"/>
                        </svg>
                        Anstellungen erstellen
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    @endif

</div>
</div>
@endsection

