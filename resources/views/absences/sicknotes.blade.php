@extends('layouts.app')

@section('title')
    Krankmeldungen
@endsection

@section('site-title')
    Krankmeldungen
@endsection

@push('css')
    @vite('resources/css/absences.css')
@endpush

@php
    $heute = \Illuminate\Support\Carbon::today();
    $schwelle = $service->schwelle();
    // Filter für Links/Export als Query (Datumsgrenzen nur beim eigenen Zeitraum)
    $query = array_filter([
        'zeitraum' => $filter['zeitraum'],
        'von' => $filter['zeitraum'] === 'frei' ? $filter['von']->toDateString() : null,
        'bis' => $filter['zeitraum'] === 'frei' ? $filter['bis']->toDateString() : null,
        'grund' => $filter['grund'],
        'person' => $filter['person'],
        'status' => $filter['status'],
        'sort' => $filter['sort'],
        'richtung' => $filter['richtung'],
        'psort' => $filter['psort'],
        'prichtung' => $filter['prichtung'],
    ], fn ($wert) => $wert !== null && $wert !== '');
    $link = fn (array $aenderung) => route('sick_notes.index', array_filter(array_merge($query, $aenderung), fn ($w) => $w !== null && $w !== ''));
    $sortLink = fn (string $spalte, string $param = 'sort', string $richtungParam = 'richtung') => $link([
        $param => $spalte,
        $richtungParam => ($filter[$param] === $spalte && $filter[$richtungParam] === 'desc') ? 'asc' : 'desc',
        'page' => null,
    ]);
    $sortIcon = fn (string $spalte, string $param = 'sort', string $richtungParam = 'richtung') => $filter[$param] === $spalte
        ? '<i class="fas fa-sort-'.($filter[$richtungParam] === 'asc' ? 'up' : 'down').'"></i>'
        : '';
    $statusKlasse = [
        'fehlt' => 'abw-badge-red',
        'ausstehend' => 'abw-badge-amber',
        'liegt_vor' => 'abw-badge-green',
        'nicht_noetig' => 'abw-badge-gray',
    ];
    $farben = ['#2563eb', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4', '#64748b'];
    $farbe = fn (string $grund) => $farben[array_search($grund, $verlauf['gruende'], true) % count($farben)];
    $zeitraum = fn (\App\Models\Absence $a) => $a->start->format('d.m.Y').($a->end->gt($a->start) ? ' – '.$a->end->format('d.m.Y') : '');
    $initialen = fn (string $name) => collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($t) => mb_substr($t, 0, 1))->take(2)->implode('');
    $maxWochentag = max(1, ...array_values($wochentage));
    $maxDauer = max(1, ...array_values($dauer));
    $filterAktiv = $filter['grund'] !== '' || $filter['person'] || $filter['status'] !== '';
@endphp

@section('content')
<div class="abw-wrapper">

    {{-- Kopf --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="abw-page-title">Krankmeldungen</h1>
            <p class="abw-page-sub">
                {{ $filter['von']->format('d.m.Y') }} – {{ $filter['bis']->format('d.m.Y') }}
                · Gründe: {{ implode(', ', $service->gruende()) }} sowie Abwesenheiten mit verlangtem Schein
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2" data-tour="krank-aktionen">
            <button type="button" class="abw-btn abw-btn-ghost" data-tour-start="krankmeldungen" title="Kurze Einführung starten">
                <i class="fas fa-route"></i><span>Tour</span>
            </button>
            <a href="{{ route('sick_notes.export', $query) }}" class="abw-btn abw-btn-secondary" title="Liste, Personen und Statistik der aktuellen Auswahl als Excel-Datei">
                <i class="fas fa-file-excel"></i><span>Excel-Export</span>
            </a>
        </div>
    </div>

    {{-- Zeitraum + Filter --}}
    <form method="get" action="{{ route('sick_notes.index') }}" class="abw-card mb-5" data-tour="krank-filter"
          x-data="{ zeitraum: @js($filter['zeitraum']) }">
        <div class="abw-card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
            <div class="lg:col-span-2">
                <label for="kr-zeitraum" class="abw-label">Zeitraum</label>
                <select id="kr-zeitraum" name="zeitraum" class="abw-select" x-model="zeitraum"
                        @change="if (zeitraum !== 'frei') $el.form.requestSubmit()">
                    @foreach(\App\Services\KrankmeldungService::ZEITRAEUME as $key => $label)
                        <option value="{{ $key }}" @selected($filter['zeitraum'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-2" x-show.important="zeitraum === 'frei'" x-cloak>
                <div>
                    <label for="kr-von" class="abw-label">von</label>
                    <input id="kr-von" type="date" name="von" value="{{ $filter['von']->toDateString() }}" class="abw-input px-2" :disabled="zeitraum !== 'frei'">
                </div>
                <div>
                    <label for="kr-bis" class="abw-label">bis</label>
                    <input id="kr-bis" type="date" name="bis" value="{{ $filter['bis']->toDateString() }}" class="abw-input px-2" :disabled="zeitraum !== 'frei'">
                </div>
            </div>
            <div>
                <label for="kr-grund" class="abw-label">Grund</label>
                <select id="kr-grund" name="grund" class="abw-select" onchange="this.form.requestSubmit()">
                    <option value="">alle</option>
                    @foreach($gruende as $grund)
                        <option value="{{ $grund }}" @selected($filter['grund'] === $grund)>{{ $grund }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="kr-person" class="abw-label">Person</label>
                <select id="kr-person" name="person" class="abw-select" onchange="this.form.requestSubmit()">
                    <option value="">alle</option>
                    @foreach($personenAuswahl as $person)
                        <option value="{{ $person->id }}" @selected($filter['person'] === $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="kr-status" class="abw-label">Krankenschein</label>
                <select id="kr-status" name="status" class="abw-select" onchange="this.form.requestSubmit()">
                    <option value="">alle</option>
                    @foreach(\App\Services\KrankmeldungService::STATUS as $key => $label)
                        <option value="{{ $key }}" @selected($filter['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="abw-btn abw-btn-secondary flex-1"><i class="fas fa-filter"></i><span>Anwenden</span></button>
                @if($filterAktiv || $filter['zeitraum'] !== '12monate')
                    <a href="{{ route('sick_notes.index') }}" class="abw-btn abw-btn-ghost" title="Filter zurücksetzen"><i class="fas fa-undo"></i></a>
                @endif
            </div>
        </div>
        @if($errors->any())
            <div class="px-4 pb-4 sm:px-5">
                <div class="abw-alert abw-alert-danger"><i class="fas fa-exclamation-triangle mt-0.5"></i><span>{{ $errors->first() }}</span></div>
            </div>
        @endif
    </form>

    {{-- Kennzahlen --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-6 mb-5" data-tour="krank-kennzahlen">
        <div class="abw-stat">
            <span>
                <span class="abw-stat-value block">{{ $kennzahlen['faelle'] }}</span>
                <span class="abw-stat-label block">Krankmeldungen</span>
            </span>
        </div>
        <div class="abw-stat" title="Arbeitstage ohne Wochenenden und Feiertage, nur innerhalb des Zeitraums">
            <span>
                <span class="abw-stat-value block">{{ $kennzahlen['tage'] }}</span>
                <span class="abw-stat-label block">Krankheitstage (Arbeitstage)</span>
            </span>
        </div>
        <div class="abw-stat">
            <span>
                <span class="abw-stat-value block">{{ $kennzahlen['personen'] }}</span>
                <span class="abw-stat-label block">betroffene Personen · Ø {{ number_format($kennzahlen['tage_je_person'], 1, ',', '.') }} Tage</span>
            </span>
        </div>
        <div class="abw-stat">
            <span>
                <span class="abw-stat-value block">{{ number_format($kennzahlen['durchschnitt'], 1, ',', '.') }}</span>
                <span class="abw-stat-label block">Ø Arbeitstage je Meldung · {{ $kennzahlen['langzeit'] }} über 30</span>
            </span>
        </div>
        <a href="{{ $link(['status' => 'fehlt', 'page' => null]) }}" class="abw-stat {{ $kennzahlen['fehlt'] > 0 ? 'border-red-200 bg-red-50' : '' }}">
            <span>
                <span class="abw-stat-value block {{ $kennzahlen['fehlt'] > 0 ? 'text-red-700' : '' }}">{{ $kennzahlen['fehlt'] }}</span>
                <span class="abw-stat-label block">Krankenscheine fehlen{{ $kennzahlen['ausstehend'] ? ' · '.$kennzahlen['ausstehend'].' noch nicht fällig' : '' }}</span>
            </span>
        </a>
        <div class="abw-stat" title="Eigene Krankheit unter {{ $schwelle }} Arbeitstagen, ohne Schein">
            <span>
                <span class="abw-stat-value block">{{ $kennzahlen['kurz'] }}</span>
                <span class="abw-stat-label block">Kurzerkrankungen ohne Schein ({{ $kennzahlen['kurz_anteil'] }} %)</span>
            </span>
        </div>
    </div>

    {{-- Auswertungen --}}
    @if($kennzahlen['faelle'] > 0)
        <div class="grid gap-5 lg:grid-cols-3 mb-5" data-tour="krank-auswertung">
            <div class="abw-card lg:col-span-3">
                <div class="abw-card-head">
                    <h2 class="abw-card-title"><i class="fas fa-chart-bar"></i> Krankheitstage je Monat</h2>
                    <div class="flex flex-wrap gap-3">
                        @foreach($verlauf['gruende'] as $grund)
                            <span class="abw-legende"><i style="background: {{ $farbe($grund) }}"></i>{{ $grund }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="abw-card-body overflow-x-auto">
                    <div class="abw-saeulen" style="min-width: {{ count($verlauf['monate']) * 2.25 }}rem">
                        @foreach($verlauf['monate'] as $monat)
                            @php $hoehe = $monat['gesamt'] / $verlauf['max'] * 100; @endphp
                            <div class="abw-saeule"
                                 title="{{ $monat['label'] }}: {{ $monat['gesamt'] }} Arbeitstage, {{ $monat['faelle'] }} neue Krankmeldungen{{ collect($monat['gruende'])->filter()->map(fn ($t, $g) => "\n".$g.': '.$t)->implode('') }}">
                                <div class="flex-1 relative">
                                    @if($monat['gesamt'] > 0)
                                        <span class="abw-saeule-wert" style="bottom: calc({{ $hoehe }}% + 2px)">{{ $monat['gesamt'] }}</span>
                                    @endif
                                </div>
                                <div class="abw-saeule-stapel" style="height: {{ $hoehe }}%; position: absolute; bottom: 1.25rem; left: 0; right: 0;">
                                    @foreach($monat['gruende'] as $grund => $tage)
                                        @if($tage > 0)
                                            <div style="flex: {{ $tage }} 1 0; background: {{ $farbe($grund) }}"></div>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="abw-saeule-label">{{ $monat['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="abw-card">
                <div class="abw-card-head">
                    <h2 class="abw-card-title"><i class="fas fa-calendar-day"></i> Beginn nach Wochentag</h2>
                </div>
                <div class="abw-card-body">
                    <div class="abw-balken">
                        @foreach($wochentage as $tag => $anzahl)
                            <span class="text-gray-600 w-6">{{ $tag }}</span>
                            <span class="abw-balken-spur"><span class="abw-balken-wert block bg-blue-500" style="width: {{ $anzahl / $maxWochentag * 100 }}%"></span></span>
                            <span class="tabular-nums text-gray-700 text-right">{{ $anzahl }}</span>
                        @endforeach
                    </div>
                    <p class="abw-hint mt-3">Beginn am Wochenende zählt zum Montag.</p>
                </div>
            </div>

            <div class="abw-card">
                <div class="abw-card-head">
                    <h2 class="abw-card-title"><i class="fas fa-hourglass-half"></i> Dauer der Krankmeldungen</h2>
                </div>
                <div class="abw-card-body">
                    <div class="abw-balken">
                        @foreach($dauer as $gruppe => $anzahl)
                            <span class="text-gray-600 whitespace-nowrap">{{ $gruppe }}</span>
                            <span class="abw-balken-spur"><span class="abw-balken-wert block bg-amber-500" style="width: {{ $anzahl / $maxDauer * 100 }}%"></span></span>
                            <span class="tabular-nums text-gray-700 text-right">{{ $anzahl }}</span>
                        @endforeach
                    </div>
                    <p class="abw-hint mt-3">Arbeitstage der gesamten Krankmeldung.</p>
                </div>
            </div>

            <div class="abw-card">
                <div class="abw-card-head">
                    <h2 class="abw-card-title"><i class="fas fa-info-circle"></i> So wird gerechnet</h2>
                </div>
                <div class="abw-card-body text-sm text-gray-600 space-y-2">
                    <p><b class="text-gray-800">Krankenschein</b> ist ab dem {{ $schwelle }}. Krankheitstag nötig – gezählt in Arbeitstagen, fällig an diesem Tag – oder wenn er ausdrücklich verlangt wurde (dann ab dem ersten Tag).</p>
                    <p><b class="text-gray-800">Krankheitstage</b> sind Arbeitstage ohne Wochenenden und Feiertage – nur die Tage innerhalb des Zeitraums.</p>
                    <p><b class="text-gray-800">Kurzerkrankung</b>: eigene Krankheit ({{ implode(', ', (array) config('absences.absence_own_sick')) }}) unter {{ $schwelle }} Arbeitstagen ohne Schein.</p>
                    <p><b class="text-gray-800">BEM-Hinweis</b>: ab {{ \App\Services\KrankmeldungService::BEM_ARBEITSTAGE }} Arbeitstagen eigener Krankheit in den letzten 12 Monaten (≈ sechs Wochen, § 167 Abs. 2 SGB IX) – unabhängig vom gewählten Zeitraum.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Liste --}}
    <div class="abw-card mb-5" data-tour="krank-liste">
        <div class="abw-card-head">
            <h2 class="abw-card-title"><i class="fas fa-notes-medical"></i> Krankmeldungen</h2>
            <span class="text-xs text-gray-500">{{ $meldungen->total() }} {{ $meldungen->total() === 1 ? 'Eintrag' : 'Einträge' }}</span>
        </div>

        @if($meldungen->isEmpty())
            <div class="abw-empty">
                <i class="fas fa-notes-medical"></i>
                <span>Keine Krankmeldungen für diese Auswahl.</span>
            </div>
        @else
            <div class="hidden lg:block overflow-x-auto">
                <table class="abw-table">
                    <thead>
                        <tr>
                            <th><a href="{{ $sortLink('name') }}" @class(['abw-sort', 'is-active' => $filter['sort'] === 'name'])>Person {!! $sortIcon('name') !!}</a></th>
                            <th><a href="{{ $sortLink('reason') }}" @class(['abw-sort', 'is-active' => $filter['sort'] === 'reason'])>Grund {!! $sortIcon('reason') !!}</a></th>
                            <th><a href="{{ $sortLink('start') }}" @class(['abw-sort', 'is-active' => $filter['sort'] === 'start'])>Zeitraum {!! $sortIcon('start') !!}</a></th>
                            <th class="text-right"><a href="{{ $sortLink('tage') }}" @class(['abw-sort', 'is-active' => $filter['sort'] === 'tage'])>Arbeitstage {!! $sortIcon('tage') !!}</a></th>
                            <th><a href="{{ $sortLink('status') }}" @class(['abw-sort', 'is-active' => $filter['sort'] === 'status'])>Krankenschein {!! $sortIcon('status') !!}</a></th>
                            <th class="text-right">Erfassen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($meldungen as $absence)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="abw-avatar">{{ $initialen($absence->user->name) }}</span>
                                        <span class="font-semibold text-gray-900">{{ $absence->user->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $absence->reason }}</td>
                                <td class="whitespace-nowrap">
                                    {{ $zeitraum($absence) }}
                                    <div class="text-xs text-gray-500">{{ $service->kalendertage($absence) }} {{ $service->kalendertage($absence) === 1 ? 'Kalendertag' : 'Kalendertage' }}</div>
                                </td>
                                <td class="text-right tabular-nums">{{ $service->arbeitstage($absence) }}</td>
                                <td>@include('absences.partials.krankenschein-status')</td>
                                <td class="text-right">@include('absences.partials.krankenschein-aktionen')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="lg:hidden abw-list">
                @foreach($meldungen as $absence)
                    <li class="abw-item">
                        <span class="abw-avatar">{{ $initialen($absence->user->name) }}</span>
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-semibold text-gray-900">{{ $absence->user->name }}</div>
                                <span class="text-sm text-gray-600">{{ $absence->reason }}</span>
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $zeitraum($absence) }} · {{ $service->arbeitstage($absence) }} Arbeitstage ({{ $service->kalendertage($absence) }} Kalendertage)
                            </div>
                            <div>@include('absences.partials.krankenschein-status')</div>
                            @include('absences.partials.krankenschein-aktionen')
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($meldungen->hasPages())
            <div class="flex items-center justify-between gap-2 px-4 py-3 sm:px-5 border-t border-gray-100">
                @if($meldungen->onFirstPage())
                    <span class="abw-btn abw-btn-secondary abw-btn-sm opacity-50"><i class="fas fa-chevron-left"></i><span class="hidden sm:inline">Zurück</span></span>
                @else
                    <a href="{{ $meldungen->previousPageUrl() }}" class="abw-btn abw-btn-secondary abw-btn-sm"><i class="fas fa-chevron-left"></i><span class="hidden sm:inline">Zurück</span></a>
                @endif
                <span class="text-xs text-gray-500">Seite {{ $meldungen->currentPage() }} von {{ $meldungen->lastPage() }}</span>
                @if($meldungen->hasMorePages())
                    <a href="{{ $meldungen->nextPageUrl() }}" class="abw-btn abw-btn-secondary abw-btn-sm"><span class="hidden sm:inline">Weiter</span><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="abw-btn abw-btn-secondary abw-btn-sm opacity-50"><span class="hidden sm:inline">Weiter</span><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    {{-- Personen --}}
    <div class="abw-card" data-tour="krank-personen">
        <div class="abw-card-head">
            <h2 class="abw-card-title"><i class="fas fa-users"></i> Übersicht je Person</h2>
            <span class="text-xs text-gray-500">Tage = Arbeitstage im Zeitraum</span>
        </div>
        @if($personen->isEmpty())
            <div class="abw-empty"><i class="fas fa-users"></i><span>Keine Daten für diese Auswahl.</span></div>
        @else
            <div class="overflow-x-auto">
                <table class="abw-table">
                    <thead>
                        <tr>
                            <th><a href="{{ $sortLink('name', 'psort', 'prichtung') }}" @class(['abw-sort', 'is-active' => $filter['psort'] === 'name'])>Person {!! $sortIcon('name', 'psort', 'prichtung') !!}</a></th>
                            <th class="text-right"><a href="{{ $sortLink('faelle', 'psort', 'prichtung') }}" @class(['abw-sort', 'is-active' => $filter['psort'] === 'faelle'])>Meldungen {!! $sortIcon('faelle', 'psort', 'prichtung') !!}</a></th>
                            <th class="text-right"><a href="{{ $sortLink('tage', 'psort', 'prichtung') }}" @class(['abw-sort', 'is-active' => $filter['psort'] === 'tage'])>Tage {!! $sortIcon('tage', 'psort', 'prichtung') !!}</a></th>
                            <th class="hidden md:table-cell">Aufteilung der Tage</th>
                            <th class="text-right"><a href="{{ $sortLink('fehlt', 'psort', 'prichtung') }}" @class(['abw-sort', 'is-active' => $filter['psort'] === 'fehlt'])>Schein fehlt {!! $sortIcon('fehlt', 'psort', 'prichtung') !!}</a></th>
                            <th class="text-right hidden sm:table-cell"><a href="{{ $sortLink('kurz', 'psort', 'prichtung') }}" @class(['abw-sort', 'is-active' => $filter['psort'] === 'kurz'])>Kurz {!! $sortIcon('kurz', 'psort', 'prichtung') !!}</a></th>
                            <th class="text-right hidden sm:table-cell"><a href="{{ $sortLink('laengste', 'psort', 'prichtung') }}" @class(['abw-sort', 'is-active' => $filter['psort'] === 'laengste'])>Längste {!! $sortIcon('laengste', 'psort', 'prichtung') !!}</a></th>
                            <th class="hidden md:table-cell">Letzte</th>
                            <th class="text-right"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personen as $person)
                            <tr>
                                <td>
                                    <a href="{{ $link(['person' => $person['user_id'], 'page' => null]) }}" class="font-semibold text-gray-900 hover:text-blue-700" title="Nur Krankmeldungen von {{ $person['name'] }} anzeigen">{{ $person['name'] }}</a>
                                    @if($person['bem'] !== null)
                                        <div>
                                            <span class="abw-badge abw-badge-violet mt-1" data-tour="krank-bem"
                                                  title="{{ $person['bem'] }} Arbeitstage eigene Krankheit in den letzten 12 Monaten – Angebot eines betrieblichen Eingliederungsmanagements prüfen (§ 167 Abs. 2 SGB IX)">
                                                <i class="fas fa-hands-helping"></i> BEM prüfen
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $person['faelle'] }}</td>
                                <td class="text-right tabular-nums font-semibold">{{ $person['tage'] }}</td>
                                <td class="hidden md:table-cell min-w-40">
                                    @if($person['tage'] > 0)
                                        <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100"
                                             title="mit Schein {{ $person['liegt_vor'] }} · ohne (nicht nötig) {{ $person['nicht_noetig'] }} · fehlt {{ $person['fehlt'] }} · noch nicht fällig {{ $person['ausstehend'] }}">
                                            <span class="bg-emerald-500" style="width: {{ $person['liegt_vor'] / $person['tage'] * 100 }}%"></span>
                                            <span class="bg-gray-400" style="width: {{ $person['nicht_noetig'] / $person['tage'] * 100 }}%"></span>
                                            <span class="bg-red-500" style="width: {{ $person['fehlt'] / $person['tage'] * 100 }}%"></span>
                                            <span class="bg-amber-400" style="width: {{ $person['ausstehend'] / $person['tage'] * 100 }}%"></span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums {{ $person['fehlt'] > 0 ? 'text-red-700 font-semibold' : '' }}">{{ $person['fehlt'] }}</td>
                                <td class="text-right tabular-nums hidden sm:table-cell">{{ $person['kurz'] }}</td>
                                <td class="text-right tabular-nums hidden sm:table-cell">{{ $person['laengste'] }}</td>
                                <td class="hidden md:table-cell whitespace-nowrap">{{ $person['letzte']->format('d.m.Y') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('sick_notes.export.user', array_merge(['user' => $person['user_id']], \Illuminate\Support\Arr::only($query, ['zeitraum', 'von', 'bis']))) }}"
                                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-emerald-600 hover:bg-emerald-50" title="Excel-Export für {{ $person['name'] }}">
                                        <i class="fas fa-file-excel"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap gap-3 px-4 py-3 sm:px-5 border-t border-gray-100">
                <span class="abw-legende"><i class="bg-emerald-500"></i>mit Schein</span>
                <span class="abw-legende"><i class="bg-gray-400"></i>ohne Schein (nicht nötig)</span>
                <span class="abw-legende"><i class="bg-red-500"></i>Schein fehlt</span>
                <span class="abw-legende"><i class="bg-amber-400"></i>noch nicht fällig</span>
            </div>
        @endif
    </div>
</div>

<x-tour id="krankmeldungen" :version="1" :steps="[
    [
        'target' => 'krank-filter',
        'title' => 'Zeitraum frei wählen',
        'text' => 'Statt fest „seit einem Jahr“ wählst du jetzt den Zeitraum: letzte 12 Monate, Schuljahr, Kalenderjahr, Vorjahr oder eigene Daten. Grund, Person und Schein-Status filtern alles auf der Seite – auch den Export.',
    ],
    [
        'target' => 'krank-kennzahlen',
        'title' => 'Kennzahlen',
        'text' => 'Krankmeldungen, Krankheitstage (Arbeitstage im Zeitraum), betroffene Personen, durchschnittliche Dauer, fehlende Scheine und Kurzerkrankungen auf einen Blick. Ein Klick auf „Krankenscheine fehlen“ zeigt genau diese Meldungen.',
    ],
    $kennzahlen['faelle'] > 0 ? [
        'target' => 'krank-auswertung',
        'title' => 'Neue Auswertungen',
        'text' => 'Krankheitstage je Monat nach Grund, der Wochentag des Beginns und die Verteilung nach Dauer. Fährst du mit der Maus über eine Säule, siehst du die Zahlen im Detail.',
    ] : null,
    [
        'target' => 'krank-liste',
        'title' => 'Einheitliche Schein-Regel',
        'text' => 'Ein Schein ist ab dem '.$schwelle.'. Krankheitstag nötig (gezählt in Arbeitstagen) oder wenn er verlangt wurde. „Noch nicht fällig“ heißt: nötig, aber der Stichtag ist noch nicht erreicht.',
    ],
    [
        'target' => 'krank-schein',
        'title' => 'Schein mit Datum erfassen',
        'text' => 'Wähle das Eingangsdatum und erfasse den Schein – vorher wurde immer das heutige Datum gesetzt. Für kurze Krankheiten kannst du den Schein auch ab dem ersten Tag verlangen.',
    ],
    [
        'target' => 'krank-personen',
        'title' => 'Übersicht je Person',
        'text' => 'Meldungen, Tage mit Aufteilung nach Schein-Status, Kurzerkrankungen, längste Krankheit und letzte Meldung – sortierbar per Klick auf die Spalte. Ein Klick auf den Namen filtert die Seite auf diese Person.',
    ],
    [
        'target' => 'krank-bem',
        'title' => 'BEM-Hinweis',
        'text' => 'Wer in den letzten 12 Monaten mehr als etwa sechs Wochen selbst krank war, bekommt diesen Hinweis: Dann ist ein betriebliches Eingliederungsmanagement anzubieten (§ 167 Abs. 2 SGB IX).',
    ],
    [
        'target' => 'krank-aktionen',
        'title' => 'Excel-Export',
        'text' => 'Der Export enthält die gefilterte Liste, die Personenübersicht und ein Statistik-Blatt mit Kennzahlen, Monatsverlauf, Wochentagen und Dauer.',
    ],
]" />
@endsection
