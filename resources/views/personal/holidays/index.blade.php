@extends('layouts.app')

@section('title')
    Urlaub
@endsection

@section('site-title')
    Urlaubsverwaltung
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $fmt = fn ($wert) => \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $wert);
    $vormonat = $monat->copy()->subMonth();
    $folgemonat = $monat->copy()->addMonth();
    $statusBadge = [
        'beantragt' => 'zw-badge-amber',
        'genehmigt' => 'zw-badge-green',
        'abgelehnt' => 'zw-badge-red',
        'storno_beantragt' => 'zw-badge-violet',
    ];
@endphp

@section('content')
<div class="zeit-wrapper">

    {{-- Kopf --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="zw-page-title">Urlaub</h1>
            <p class="zw-page-sub">Urlaub beantragen, Resturlaub im Blick behalten und sehen, wer wann weg ist.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($konto)
                <a href="{{ route('holidays.account', [auth()->id(), $monat->year]) }}" class="zw-btn zw-btn-secondary">
                    <i class="fas fa-wallet"></i><span>Mein Urlaubskonto</span>
                </a>
            @endif
            @can('approve holidays')
                <a href="{{ route('holidays.manage') }}" class="zw-btn zw-btn-secondary">
                    <i class="fas fa-tasks"></i><span>Verwaltung</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Kennzahlen Urlaubskonto --}}
    @if($konto)
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
            <div class="zw-stat">
                <span class="zw-stat-value">{{ $fmt($konto['anspruch']) }}</span>
                <span class="zw-stat-label">Anspruch {{ $konto['jahr'] }}</span>
            </div>
            <div class="zw-stat">
                <span class="zw-stat-value">{{ $fmt($konto['uebertrag']) }}</span>
                <span class="zw-stat-label">Übertrag Vorjahr</span>
            </div>
            <div class="zw-stat">
                <span class="zw-stat-value">{{ $fmt($konto['genommen']) }}</span>
                <span class="zw-stat-label">genehmigt / genommen</span>
            </div>
            <div class="zw-stat">
                <span class="zw-stat-value">{{ $fmt($konto['beantragt']) }}</span>
                <span class="zw-stat-label">beantragt (offen)</span>
            </div>
            <div class="zw-stat {{ $konto['rest'] < 0 ? 'is-negative' : 'is-positive' }}">
                <span class="zw-stat-value">{{ $fmt($konto['rest']) }}</span>
                <span class="zw-stat-label">Resturlaub</span>
            </div>
            <div class="zw-stat">
                <span class="zw-stat-value">{{ $fmt($konto['rest_nach_antraegen']) }}</span>
                <span class="zw-stat-label">Rest nach offenen Anträgen</span>
            </div>
        </div>

        @if($konto['verfall_droht'] > 0 && $konto['verfallsdatum'])
            <div class="zw-alert zw-alert-warning mb-5">
                <i class="fas fa-hourglass-half mt-0.5"></i>
                <div>
                    <strong>{{ $fmt($konto['verfall_droht']) }} Tag(e) Resturlaub aus dem Vorjahr</strong>
                    verfallen, wenn sie nicht bis zum {{ $konto['verfallsdatum']->format('d.m.Y') }} genommen werden.
                </div>
            </div>
        @endif
        @if($konto['verfallen'] > 0)
            <div class="zw-alert zw-alert-info mb-5">
                <i class="fas fa-info-circle mt-0.5"></i>
                <div>{{ $fmt($konto['verfallen']) }} Tag(e) Übertrag sind zum {{ $konto['verfallsdatum']?->format('d.m.Y') }} verfallen.</div>
            </div>
        @endif
    @endif

    {{-- Zu entscheiden --}}
    @if($zuEntscheiden->isNotEmpty())
        <section id="entscheiden" class="zw-card mb-5 border-amber-200">
            <div class="zw-card-head bg-amber-50 rounded-t-2xl">
                <h2 class="zw-card-title"><i class="fas fa-inbox text-amber-500"></i> Zu entscheiden</h2>
                <span class="zw-badge zw-badge-amber">{{ $zuEntscheiden->count() }} offen</span>
            </div>
            <ul class="zw-list">
                @foreach($zuEntscheiden as $antrag)
                    <li class="px-4 py-3 sm:px-5" x-data="{ ablehnen: false }">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-gray-900">{{ $antrag->employe->name }}</div>
                                <div class="text-sm text-gray-600">
                                    {{ $antrag->start_date->format('d.m.Y') }}@if(!$antrag->start_date->isSameDay($antrag->end_date)) – {{ $antrag->end_date->format('d.m.Y') }}@endif
                                    · {{ $antrag->days_label }}@if($antrag->half_day) (halber Tag)@endif
                                </div>
                                @if($antrag->cancellation_requested_at)
                                    <div class="mt-1 text-sm text-violet-700"><i class="fas fa-undo mr-1"></i>Stornierung beantragt{{ $antrag->cancellation_reason ? ': '.$antrag->cancellation_reason : '' }}</div>
                                @elseif($antrag->comment)
                                    <div class="mt-1 text-sm text-gray-500"><i class="far fa-comment mr-1"></i>{{ $antrag->comment }}</div>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if($antrag->cancellation_requested_at)
                                    <form action="{{ route('holidays.cancel-decision', $antrag) }}" method="post">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-success"><i class="fas fa-check"></i> Stornierung annehmen</button>
                                    </form>
                                    <form action="{{ route('holidays.cancel-decision', $antrag) }}" method="post">
                                        @csrf
                                        <input type="hidden" name="decision" value="deny">
                                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-secondary">Urlaub behalten</button>
                                    </form>
                                @else
                                    <form action="{{ route('holidays.approve', $antrag) }}" method="post">
                                        @csrf
                                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-success"><i class="fas fa-check"></i> Genehmigen</button>
                                    </form>
                                    <button type="button" class="zw-btn zw-btn-sm zw-btn-secondary" @click="ablehnen = !ablehnen">
                                        <i class="fas fa-times"></i> Ablehnen …
                                    </button>
                                @endif
                            </div>
                        </div>
                        <form x-show.important="ablehnen" x-cloak action="{{ route('holidays.reject', $antrag) }}" method="post" class="mt-3 flex flex-col sm:flex-row gap-2">
                            @csrf
                            <input type="text" name="reason" maxlength="255" class="zw-input" placeholder="Begründung (wird der Person mitgeteilt)">
                            <button type="submit" class="zw-btn zw-btn-danger shrink-0">Ablehnen</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 mb-5">

        {{-- Antrag --}}
        @if($antragFuer->isNotEmpty() || $darfFuerAlle)
            @php $standardPerson = old('employe_id', $antragFuer->first()?->id); @endphp
            <section class="zw-card lg:col-span-2"
                     x-data="urlaubsAntrag({ previewUrl: @js(route('holidays.preview')), employe: @js((string) $standardPerson), start: @js(old('start_date', '')), end: @js(old('end_date', '')) })">
                <div class="zw-card-head">
                    <h2 class="zw-card-title"><i class="fas fa-umbrella-beach"></i> Urlaub beantragen</h2>
                </div>
                <form action="{{ route('holidays.store') }}" method="post" class="zw-card-body flex flex-col gap-4">
                    @csrf

                    @if($antragFuer->count() > 1 || $darfFuerAlle)
                        <div>
                            <label for="employe_id" class="zw-label">Für</label>
                            <select name="employe_id" id="employe_id" class="zw-select" x-model="employe">
                                @foreach($antragFuer as $person)
                                    <option value="{{ $person->id }}">{{ $person->id === auth()->id() ? 'Mich selbst' : $person->name }}</option>
                                @endforeach
                                @if($darfFuerAlle)
                                    <option value="all">Alle / Gruppe (z. B. Betriebsferien)</option>
                                @endif
                            </select>
                        </div>
                        @if($darfFuerAlle)
                            <div x-show="fuerAlle" x-cloak>
                                <label for="group_id" class="zw-label">Gruppe (optional)</label>
                                <select name="group_id" id="group_id" class="zw-select">
                                    <option value="">Alle mit Urlaubsanspruch</option>
                                    @foreach($gruppen as $gruppe)
                                        <option value="{{ $gruppe->id }}">{{ $gruppe->name }}</option>
                                    @endforeach
                                </select>
                                <p class="zw-hint">Wird direkt genehmigt eingetragen. Bestehende Überschneidungen werden übersprungen.</p>
                            </div>
                        @endif
                    @else
                        <input type="hidden" name="employe_id" value="{{ $standardPerson }}">
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="start_date" class="zw-label">Von</label>
                            <input type="date" name="start_date" id="start_date" class="zw-input @error('start_date') is-invalid @enderror" x-model="start" required>
                        </div>
                        <div>
                            <label for="end_date" class="zw-label">Bis</label>
                            <input type="date" name="end_date" id="end_date" class="zw-input @error('end_date') is-invalid @enderror" x-model="end" :min="start" required>
                        </div>
                    </div>
                    @error('start_date') <p class="zw-error -mt-2">{{ $message }}</p> @enderror
                    @error('end_date') <p class="zw-error -mt-2">{{ $message }}</p> @enderror

                    <label class="zw-check self-start" x-show.important="eintaegig" x-cloak>
                        <input type="checkbox" name="half_day" value="1" x-model="halberTag">
                        Nur halber Tag
                    </label>

                    <div>
                        <label for="comment" class="zw-label">Bemerkung <span class="font-normal text-gray-400">(optional)</span></label>
                        <input type="text" name="comment" id="comment" maxlength="255" class="zw-input" value="{{ old('comment') }}" placeholder="z. B. Hochzeit, Kur …">
                    </div>

                    {{-- Live-Vorschau --}}
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm" x-show="vorschau || laedt || fehler" x-cloak>
                        <template x-if="laedt"><p class="text-gray-500"><i class="fas fa-spinner fa-spin mr-1"></i> Berechne …</p></template>
                        <template x-if="fehler"><p class="text-red-600" x-text="fehler"></p></template>
                        <template x-if="vorschau && !laedt">
                            <div class="flex flex-col gap-2">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span><strong class="text-lg" x-text="zahl(vorschau.tage)"></strong> Urlaubstag(e)</span>
                                    <span class="text-gray-600">Rest danach: <strong :class="vorschau.rest_nachher < 0 ? 'text-red-600' : 'text-emerald-700'" x-text="zahl(vorschau.rest_nachher)"></strong></span>
                                </div>
                                <p class="text-red-600" x-show="vorschau.tage === 0">Im Zeitraum liegen keine Arbeitstage.</p>
                                <p class="text-red-600" x-show="vorschau.ueberschneidung_eigen">Für diesen Zeitraum gibt es bereits einen Antrag.</p>
                                <p class="text-amber-700" x-show="vorschau.rest_nachher < 0">Der Antrag übersteigt den verfügbaren Resturlaub.</p>
                                <template x-if="vorschau.team.length">
                                    <div>
                                        <p class="font-semibold text-gray-700 mb-1"><i class="fas fa-users mr-1 text-gray-400"></i>Ebenfalls weg:</p>
                                        <ul class="flex flex-col gap-0.5">
                                            <template x-for="t in vorschau.team" :key="t.name + t.von">
                                                <li class="text-gray-600"><span x-text="t.name"></span> (<span x-text="t.von"></span>–<span x-text="t.bis"></span>, <span x-text="t.status"></span>)</li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <button type="submit" class="zw-btn zw-btn-primary w-full" :disabled="vorschau && (vorschau.tage === 0 || vorschau.ueberschneidung_eigen)">
                        <i class="fas fa-paper-plane"></i> <span x-text="fuerAlle ? 'Für alle eintragen' : 'Urlaub beantragen'">Urlaub beantragen</span>
                    </button>
                </form>
            </section>
        @endif

        {{-- Meine Anträge --}}
        <section class="zw-card {{ $antragFuer->isNotEmpty() || $darfFuerAlle ? 'lg:col-span-3' : 'lg:col-span-5' }}">
            <div class="zw-card-head">
                <h2 class="zw-card-title"><i class="fas fa-list"></i> Meine Anträge {{ $monat->year }}</h2>
                <div class="flex items-center gap-1">
                    <a href="{{ route('holidays.index', [$monat->month, $monat->year - 1]) }}" class="zw-btn-icon is-sm" title="Vorjahr"><i class="fas fa-chevron-left"></i></a>
                    <a href="{{ route('holidays.index', [$monat->month, $monat->year + 1]) }}" class="zw-btn-icon is-sm" title="Folgejahr"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            @if($eigeneAntraege->isEmpty())
                <div class="zw-empty"><i class="fas fa-umbrella-beach"></i> Keine Anträge in {{ $monat->year }}.</div>
            @else
                <ul class="zw-list">
                    @foreach($eigeneAntraege as $antrag)
                        <li class="px-4 py-3 sm:px-5 {{ $antrag->trashed() ? 'opacity-60' : '' }}" x-data="{ storno: false }">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <div class="text-center shrink-0 w-14">
                                    <div class="text-sm font-bold text-gray-900">{{ $antrag->start_date->format('d.m.') }}</div>
                                    @if(!$antrag->start_date->isSameDay($antrag->end_date))
                                        <div class="text-xs text-gray-500">– {{ $antrag->end_date->format('d.m.') }}</div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm text-gray-700">{{ $antrag->days_label }}@if($antrag->half_day) · halber Tag @endif</div>
                                    @if($antrag->comment)<div class="text-xs text-gray-500 truncate">{{ $antrag->comment }}</div>@endif
                                    @if($antrag->rejected && $antrag->rejection_reason)
                                        <div class="text-xs text-red-600">Begründung: {{ $antrag->rejection_reason }}</div>
                                    @endif
                                </div>
                                @if($antrag->trashed())
                                    <span class="zw-badge zw-badge-gray">Storniert</span>
                                @else
                                    <span class="zw-badge {{ $statusBadge[$antrag->status] }}">{{ $antrag->status_label }}</span>
                                @endif
                                @unless($antrag->trashed())
                                    <div class="flex items-center gap-1">
                                        @can('delete', $antrag)
                                            <form action="{{ route('holidays.destroy', $antrag) }}" method="post" data-confirm="{{ $antrag->is_pending ? 'Antrag zurückziehen?' : 'Eintrag entfernen?' }}">
                                                @csrf @method('delete')
                                                <button type="submit" class="zw-btn zw-btn-sm zw-btn-danger-ghost" title="{{ $antrag->is_pending ? 'Antrag zurückziehen' : 'Entfernen' }}">
                                                    <i class="fas fa-trash"></i><span class="hidden sm:inline">{{ $antrag->is_pending ? 'Zurückziehen' : 'Entfernen' }}</span>
                                                </button>
                                            </form>
                                        @endcan
                                        @can('requestCancellation', $antrag)
                                            <button type="button" class="zw-btn zw-btn-sm zw-btn-secondary" @click="storno = !storno">
                                                <i class="fas fa-undo"></i><span class="hidden sm:inline">Stornieren …</span>
                                            </button>
                                        @endcan
                                    </div>
                                @endunless
                            </div>
                            @can('requestCancellation', $antrag)
                                <form x-show.important="storno" x-cloak action="{{ route('holidays.cancel', $antrag) }}" method="post" class="mt-3 flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    <input type="text" name="reason" maxlength="255" class="zw-input" placeholder="Grund (optional)">
                                    <button type="submit" class="zw-btn zw-btn-warning shrink-0">Stornierung beantragen</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Teamkalender --}}
    <section class="zw-card" x-data="teamKalender()">
        <div class="zw-card-head">
            <div class="flex items-center gap-2">
                <a href="{{ route('holidays.index', [$vormonat->month, $vormonat->year]) }}" class="zw-btn-icon" title="Vormonat"><i class="fas fa-chevron-left"></i></a>
                <h2 class="zw-card-title min-w-[10rem] justify-center">{{ $monat->locale('de')->isoFormat('MMMM YYYY') }}</h2>
                <a href="{{ route('holidays.index', [$folgemonat->month, $folgemonat->year]) }}" class="zw-btn-icon" title="Folgemonat"><i class="fas fa-chevron-right"></i></a>
                @unless($monat->isSameMonth(now()))
                    <a href="{{ route('holidays.index') }}" class="zw-btn zw-btn-sm zw-btn-ghost">Heute</a>
                @endunless
            </div>
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <input type="search" x-model="suche" placeholder="Name suchen" class="zw-input sm:w-44">
                @if($gruppen->isNotEmpty())
                    <select x-model="gruppe" class="zw-select sm:w-48">
                        <option value="alle">Alle Gruppen</option>
                        @foreach($gruppen as $gruppe)
                            <option value="{{ $gruppe->id }}">{{ $gruppe->name }}</option>
                        @endforeach
                    </select>
                @endif
                @can('approve holidays')
                    <a :href="@js(route('holidays.export', $monat->year)) + (gruppe !== 'alle' ? '/' + gruppe : '')" class="zw-btn zw-btn-sm zw-btn-secondary" title="Jahresübersicht als PDF">
                        <i class="fas fa-file-pdf"></i><span class="hidden md:inline">PDF {{ $monat->year }}</span>
                    </a>
                @endcan
            </div>
        </div>

        @if($kalenderNutzer->isEmpty())
            <div class="zw-empty"><i class="fas fa-users"></i> Keine Personen zur Anzeige.</div>
        @else
            <div class="zw-cal">
                <table>
                    <thead>
                    <tr>
                        <th class="zw-cal-name">Name</th>
                        @foreach($tage as $tag)
                            <th class="{{ $tag['frei'] ? 'is-frei' : ($tag['ferien'] ? 'is-ferien' : '') }} {{ $tag['date']->isToday() ? 'is-heute' : '' }}"
                                title="{{ $tag['feiertag'] ?? $tag['ferien'] ?? '' }}">
                                <div class="leading-tight">{{ $tag['date']->day }}</div>
                                <div class="text-[10px] font-normal text-gray-400">{{ $tag['date']->locale('de')->isoFormat('dd') }}</div>
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($kalenderNutzer as $person)
                        @php $urlaube = $kalenderUrlaube->get($person->id, collect()); @endphp
                        <tr x-show="sichtbar(@js($person->groups_rel->pluck('id')), @js($person->name))">
                            <td class="zw-cal-name {{ $person->id === auth()->id() ? 'font-bold' : '' }}" title="{{ $person->name }}">{{ $person->name }}</td>
                            @foreach($tage as $tag)
                                @php $urlaub = $tag['frei'] ? null : $urlaube->first(fn ($h) => $h->start_date->lte($tag['date']) && $h->end_date->gte($tag['date'])); @endphp
                                <td class="{{ $tag['frei'] ? 'is-frei' : ($tag['ferien'] ? 'is-ferien' : '') }}">
                                    @if($urlaub)
                                        @php $klasse = match($urlaub->status) { 'genehmigt' => 'u-genehmigt', 'storno_beantragt' => 'u-storno', default => 'u-beantragt' }; @endphp
                                        <span class="zw-cal-u {{ $klasse }}" title="{{ $person->name }}: {{ $urlaub->start_date->format('d.m.') }}–{{ $urlaub->end_date->format('d.m.Y') }} ({{ $urlaub->status_label }})">{{ $urlaub->half_day ? '½' : 'U' }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-xs text-gray-600 border-t border-gray-100">
                <span class="inline-flex items-center gap-1.5"><span class="zw-legend-dot bg-emerald-500"></span> genehmigt</span>
                <span class="inline-flex items-center gap-1.5"><span class="zw-legend-dot bg-amber-400"></span> beantragt</span>
                <span class="inline-flex items-center gap-1.5"><span class="zw-legend-dot bg-violet-400"></span> Stornierung beantragt</span>
                <span class="inline-flex items-center gap-1.5"><span class="zw-legend-dot bg-slate-200"></span> Wochenende/Feiertag</span>
                <span class="inline-flex items-center gap-1.5"><span class="zw-legend-dot bg-blue-50 border border-blue-200"></span> Ferien</span>
                <span class="sm:hidden text-gray-400"><i class="fas fa-arrows-alt-h"></i> seitlich wischen</span>
            </div>
        @endif
    </section>
</div>
@endsection
