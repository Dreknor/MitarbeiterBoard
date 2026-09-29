@extends('layouts.app')

@section('title')
    Arbeitszeitnachweis
@endsection

@section('site-title')
    Arbeitszeitnachweis
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $hm = function ($sekunden, bool $vorzeichen = false) {
        $minuten = (int) round($sekunden / 60);
        $zeichen = $minuten < 0 ? '−' : ($vorzeichen && $minuten > 0 ? '+' : '');
        $minuten = abs($minuten);
        return $zeichen.intdiv($minuten, 60).':'.str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT);
    };
    $fmt = fn ($wert) => \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $wert);
    $darfBearbeiten = auth()->user()->can('edit', $timesheet);
    $gezaehlt = $zeilen->where('zaehlt', true);
    $sollMonat = $gezaehlt->sum('soll');
    $istMonat = $gezaehlt->sum('ist');
    $saldoVorher = (int) ($timesheet_old?->working_time_account ?? 0);
    $statusBadge = ['offen' => 'zw-badge-gray', 'eingereicht' => 'zw-badge-blue', 'abgeschlossen' => 'zw-badge-green'][$timesheet->status];
    $offeneTage = $zeilen->filter(fn ($z) => $z['zaehlt'] && $z['plan'] && $z['entries']->isEmpty())->count();
@endphp

@section('content')
<div class="zeit-wrapper">

    {{-- Kopf --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div class="min-w-0">
            @unless($istEigener)
                <a href="{{ route('timesheets.index') }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>Alle Nachweise</a>
            @endunless
            <h1 class="zw-page-title mt-1">Arbeitszeitnachweis{{ $istEigener ? '' : ' '.$employe->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <span class="zw-badge {{ $statusBadge }}">
                    <i class="fas {{ $timesheet->is_locked ? 'fa-lock' : ($timesheet->submitted_at ? 'fa-paper-plane' : 'fa-pen') }}"></i>
                    {{ $timesheet->status_label }}
                </span>
                @if($timesheet->is_locked)
                    <span class="text-xs text-gray-500">von {{ $timesheet->lockedBy?->name ?? '–' }} am {{ $timesheet->locked_at->format('d.m.Y') }}</span>
                @elseif($timesheet->submitted_at)
                    <span class="text-xs text-gray-500">am {{ $timesheet->submitted_at->format('d.m.Y') }}</span>
                @endif
                @if($timesheet->requires_review)
                    <span class="zw-badge zw-badge-amber" title="{{ $timesheet->review_reason }}"><i class="fas fa-exclamation-triangle"></i> Prüfung nötig</span>
                @endif
            </div>
        </div>

        {{-- Monatsauswahl --}}
        <div class="flex items-center gap-1">
            <a href="{{ route('timesheets.show', [$employe->id, $month->copy()->subMonth()->format('Y-m')]) }}" class="zw-btn-icon" title="Vormonat"><i class="fas fa-chevron-left"></i></a>
            <select class="zw-select w-auto font-semibold" onchange="window.location = this.value" aria-label="Monat wählen">
                @foreach($monate as $m)
                    <option value="{{ route('timesheets.show', [$employe->id, $m->format('Y-m')]) }}" @selected($m->isSameMonth($month))>{{ $m->locale('de')->isoFormat('MMMM YYYY') }}</option>
                @endforeach
                @unless(collect($monate)->contains(fn ($m) => $m->isSameMonth($month)))
                    <option selected>{{ $month->locale('de')->isoFormat('MMMM YYYY') }}</option>
                @endunless
            </select>
            @if($month->copy()->addMonth()->lte(now()->startOfMonth()->addMonth()))
                <a href="{{ route('timesheets.show', [$employe->id, $month->copy()->addMonth()->format('Y-m')]) }}" class="zw-btn-icon" title="Folgemonat"><i class="fas fa-chevron-right"></i></a>
            @endif
        </div>
    </div>

    {{-- Hinweise --}}
    @if($timesheet->return_reason && !$timesheet->submitted_at && !$timesheet->is_locked)
        <div class="zw-alert zw-alert-warning mb-4">
            <i class="fas fa-reply mt-0.5"></i>
            <div><strong>Zur Korrektur zurückgegeben:</strong> {{ $timesheet->return_reason }}</div>
        </div>
    @endif
    @if($timesheet->requires_review && $timesheet->review_reason)
        <div class="zw-alert zw-alert-info mb-4">
            <i class="fas fa-info-circle mt-0.5"></i>
            <div>{{ $timesheet->review_reason }}</div>
        </div>
    @endif

    @if($eingefroren)
        <div class="zw-alert zw-alert-info mb-4">
            <i class="fas fa-archive mt-0.5"></i>
            <div>
                <strong>Abgelaufener Monat vor der Umstellung:</strong> Stundenkonto und Urlaub bleiben so, wie sie gespeichert wurden.
                Erst wenn in diesem Monat etwas geändert oder „Neu berechnen“ gewählt wird, rechnet das System ihn nach der bisherigen Methode neu.
            </div>
        </div>
    @elseif($altesModell)
        <div class="zw-alert zw-alert-info mb-4">
            <i class="fas fa-info-circle mt-0.5"></i>
            <div>Dieser Monat wird noch nach der bisherigen Methode berechnet (5-Tage-Woche). Das neue Arbeitszeitmodell gilt ab dem Stichtag.</div>
        </div>
    @endif

    {{-- Kennzahlen --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
        <div class="zw-stat {{ $timesheet->working_time_account < 0 ? 'is-negative' : 'is-positive' }}">
            <span class="zw-stat-value">{{ $hm($timesheet->working_time_account, true) }} h</span>
            <span class="zw-stat-label">Stundenkonto Monatsende</span>
        </div>
        <div class="zw-stat">
            <span class="zw-stat-value {{ $timesheet->working_time_account - $saldoVorher < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $hm($timesheet->working_time_account - $saldoVorher, true) }} h</span>
            <span class="zw-stat-label">Veränderung im Monat</span>
        </div>
        <div class="zw-stat">
            <span class="zw-stat-value">{{ $hm($istMonat) }} h</span>
            <span class="zw-stat-label">Ist bis heute</span>
        </div>
        <div class="zw-stat">
            <span class="zw-stat-value">{{ $hm($sollMonat) }} h</span>
            <span class="zw-stat-label">Soll bis heute</span>
        </div>
        <div class="zw-stat">
            <span class="zw-stat-value">{{ $fmt($timesheet->holidays_new) }}</span>
            <span class="zw-stat-label">Urlaubstage im Monat</span>
        </div>
        <div class="zw-stat">
            <span class="zw-stat-value">{{ $fmt($timesheet->holidays_rest) }}</span>
            <span class="zw-stat-label">Resturlaub Monatsende</span>
        </div>
    </div>

    {{-- Aktionen / Workflow --}}
    <div class="zw-card mb-4 px-4 py-3 sm:px-5 flex flex-wrap items-center gap-2" x-data="{ zurueck: false }">
        @can('submit', $timesheet)
            <form action="{{ route('timesheets.submit', [$employe->id, $timesheet->id]) }}" method="post" data-confirm="Nachweis für {{ $month->locale('de')->isoFormat('MMMM') }} einreichen? Danach kannst du ihn nur noch nach Rückgabe ändern.">
                @csrf
                <button type="submit" class="zw-btn zw-btn-primary"><i class="fas fa-paper-plane"></i> Einreichen</button>
            </form>
        @endcan
        @can('lock', $timesheet)
            <form action="{{ route('timesheets.lock', [$employe->id, $timesheet->id]) }}" method="post" data-confirm="Nachweis bestätigen und abschließen?">
                @csrf
                <button type="submit" class="zw-btn zw-btn-success"><i class="fas fa-lock"></i> Bestätigen &amp; abschließen</button>
            </form>
        @endcan
        @can('returnToEmploye', $timesheet)
            <button type="button" class="zw-btn zw-btn-secondary" @click="zurueck = !zurueck"><i class="fas fa-reply"></i> Zurückgeben …</button>
        @endcan
        @can('unlock', $timesheet)
            <form action="{{ route('timesheets.unlock', [$employe->id, $timesheet->id]) }}" method="post" data-confirm="Sperre aufheben? Der Nachweis wird danach neu berechnet.">
                @csrf
                <button type="submit" class="zw-btn zw-btn-secondary"><i class="fas fa-lock-open"></i> Entsperren</button>
            </form>
        @endcan
        @if($darfBearbeiten && $offeneTage > 0)
            <form action="{{ route('timesheets.plan-month', [$employe->id, $timesheet->id]) }}" method="post" data-confirm="Für {{ $offeneTage }} Tag(e) ohne Buchung die Dienstplanzeiten übernehmen?">
                @csrf
                <button type="submit" class="zw-btn zw-btn-secondary"><i class="fas fa-calendar-check"></i> Dienstplan übernehmen ({{ $offeneTage }})</button>
            </form>
        @endif
        <div class="flex-1"></div>
        @if($darfBearbeiten)
            <form action="{{ route('timesheets.recalculate', [$employe->id, $timesheet->id]) }}" method="post">
                @csrf
                <button type="submit" class="zw-btn zw-btn-ghost" title="Neu berechnen und prüfen"><i class="fas fa-sync-alt"></i><span class="hidden sm:inline">Neu berechnen</span></button>
            </form>
        @endif
        <a href="{{ route('timesheets.export', [$employe->id, $timesheet->id]) }}" class="zw-btn zw-btn-ghost"><i class="fas fa-file-pdf"></i><span class="hidden sm:inline">PDF</span></a>
        <a href="{{ route('timesheets.overview', $employe->id) }}" class="zw-btn zw-btn-ghost"><i class="fas fa-chart-line"></i><span class="hidden sm:inline">Verlauf</span></a>

        @can('returnToEmploye', $timesheet)
            <form x-show.important="zurueck" x-cloak action="{{ route('timesheets.return', [$employe->id, $timesheet->id]) }}" method="post" class="basis-full flex flex-col sm:flex-row gap-2 pt-2">
                @csrf
                <input type="text" name="reason" maxlength="255" required class="zw-input" placeholder="Was soll korrigiert werden?">
                <button type="submit" class="zw-btn zw-btn-warning shrink-0">Zurückgeben</button>
            </form>
        @endcan
    </div>

    {{-- Fehlende Buchungen --}}
    @if($darfBearbeiten && $missingEntries->isNotEmpty())
        <div class="zw-alert zw-alert-warning mb-4 flex-col sm:flex-row">
            <i class="fas fa-exclamation-triangle mt-0.5"></i>
            <div class="flex-1">
                <strong>Bitte Arbeitszeiten nachtragen</strong>
                <ul class="mt-2 flex flex-col gap-2">
                    @foreach($missingEntries as $entry)
                        @php $ctx = $entry->context ?? []; @endphp
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ optional($entry->date)->locale('de')->isoFormat('dd, DD.MM.') }}</span>
                            <span class="text-amber-800">{{ $entry->description }}</span>
                            @if(!empty($ctx['suggested_start']) && !empty($ctx['suggested_end']))
                                <form action="{{ route('timesheets.day.plan', [$employe->id, $timesheet->id, $entry->date->format('Y-m-d')]) }}" method="post">
                                    @csrf
                                    <button type="submit" class="zw-btn zw-btn-sm zw-btn-secondary">Plan übernehmen ({{ $ctx['suggested_start'] }}–{{ $ctx['suggested_end'] }})</button>
                                </form>
                            @else
                                <a href="{{ route('timesheets.day.create', [$employe->id, $timesheet->id, $entry->date->format('Y-m-d')]) }}" class="zw-btn zw-btn-sm zw-btn-secondary">erfassen</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Tage --}}
    <section class="zw-card overflow-hidden">
        <div class="hidden lg:grid zw-day bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 py-2">
            <div>Tag</div>
            <div>Buchungen</div>
            <div>Bemerkung</div>
            <div class="text-right">Ist / Soll</div>
            <div class="text-right">Saldo</div>
            <div></div>
        </div>

        @foreach($zeilen as $zeile)
            @php
                $tag = $zeile['date'];
                $datum = $tag->toDateString();
                $zeitBuchungen = $zeile['entries']->reject->is_credit;
                $bemerkungen = $zeile['entries']->pluck('comment')->filter(fn ($c) => $c && !in_array($c, ['digitale Zeiterfassung', 'aus Dienstplan übernommen', 'aus Dienstplan erstellt'], true) && !$zeile['entries']->contains(fn ($e) => $e->is_credit && $e->comment === $c))->unique();
                $zeigtSaldo = $zeile['zaehlt'] && ($zeile['soll'] > 0 || $zeile['ist'] > 0);
            @endphp
            <div id="tag-{{ $datum }}" class="zw-day {{ !$zeile['arbeitstag'] ? 'is-frei' : '' }} {{ $tag->isToday() ? 'is-heute' : '' }} {{ $zeile['offen'] && !$tag->isToday() ? 'is-offen' : '' }}">
                {{-- Datum --}}
                <div class="flex items-baseline justify-between gap-2 lg:block">
                    <div>
                        <span class="font-semibold text-gray-900">{{ $tag->locale('de')->isoFormat('dd') }}, {{ $tag->format('d.m.') }}</span>
                        @if($zeile['feiertag'])<div class="text-xs text-blue-700">{{ $zeile['feiertag'] }}</div>@endif
                    </div>
                    <div class="lg:hidden text-xs text-gray-600 text-right">
                        @if($zeile['soll'] > 0 || $zeile['ist'] > 0)
                            {{ $hm($zeile['ist']) }} / {{ $hm($zeile['soll']) }} h
                            @if($zeigtSaldo)<span class="ml-1 font-semibold {{ $zeile['diff'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $hm($zeile['diff'], true) }}</span>@endif
                        @endif
                    </div>
                </div>

                {{-- Buchungen --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach($zeile['entries'] as $eintrag)
                        @if($eintrag->is_credit)
                            <span class="zw-entry is-credit" title="{{ $eintrag->is_automatic ? 'automatisch aus Urlaub/Abwesenheit' : 'manuell eingetragen' }}">
                                <i class="fas {{ $eintrag->source === 'urlaub' ? 'fa-umbrella-beach' : 'fa-user-clock' }}"></i>
                                {{ $eintrag->comment }}@if((int) $eintrag->percent_of_workingtime !== 100) ({{ $eintrag->percent_of_workingtime }} %)@endif
                                @if($darfBearbeiten && !$eintrag->is_automatic)
                                    <form action="{{ route('timesheets.day.destroy', $eintrag) }}" method="post" class="inline" data-confirm="{{ $eintrag->comment }} am {{ $tag->format('d.m.') }} entfernen?">
                                        @csrf @method('delete')
                                        <button type="submit" class="ml-1 text-emerald-700 hover:text-red-600" title="Entfernen"><i class="fas fa-times"></i></button>
                                    </form>
                                @endif
                            </span>
                        @else
                            @php $label = $eintrag->start?->format('H:i').'–'.($eintrag->end?->format('H:i') ?? '…'); @endphp
                            @if($darfBearbeiten)
                                <a href="{{ route('timesheets.day.edit', $eintrag) }}" class="zw-entry {{ $eintrag->end ? '' : 'is-open' }} hover:border-blue-400" title="bearbeiten">
                                    <i class="far fa-clock"></i> {{ $label }}@if($eintrag->pause) <span class="text-gray-400">· {{ $eintrag->pause }}′</span>@endif
                                </a>
                            @else
                                <span class="zw-entry {{ $eintrag->end ? '' : 'is-open' }}"><i class="far fa-clock"></i> {{ $label }}@if($eintrag->pause) <span class="text-gray-400">· {{ $eintrag->pause }}′</span>@endif</span>
                            @endif
                        @endif
                    @endforeach
                    @if($zeile['plan'] && $zeitBuchungen->isEmpty() && !$zeile['entries']->contains->is_credit)
                        <span class="zw-entry is-plan" title="Dienstplan (noch nicht übernommen)"><i class="far fa-calendar"></i> Plan {{ $zeile['plan']['start'] }}–{{ $zeile['plan']['end'] }}</span>
                    @endif
                    @if($bemerkungen->isNotEmpty())
                        <span class="lg:hidden text-xs text-gray-500">{{ $bemerkungen->implode(', ') }}</span>
                    @endif
                </div>

                {{-- Bemerkung (Desktop) --}}
                <div class="hidden lg:block text-xs text-gray-500 truncate" title="{{ $bemerkungen->implode(', ') }}">{{ $bemerkungen->implode(', ') }}</div>

                {{-- Ist / Soll (Desktop) --}}
                <div class="hidden lg:block text-right text-sm tabular-nums text-gray-700">
                    @if($zeile['soll'] > 0 || $zeile['ist'] > 0)
                        {{ $hm($zeile['ist']) }} <span class="text-gray-400">/ {{ $hm($zeile['soll']) }}</span>
                    @endif
                </div>

                {{-- Saldo (Desktop) --}}
                <div class="hidden lg:block text-right text-sm font-semibold tabular-nums {{ $zeile['diff'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                    @if($zeigtSaldo){{ $hm($zeile['diff'], true) }}@endif
                </div>

                {{-- Aktionen --}}
                <div class="flex items-center justify-end gap-1">
                    @if($darfBearbeiten && $tag->lte(today()))
                        @if($zeile['plan'] && ($zeitBuchungen->isEmpty() || $zeile['offen']) && ($tag->lt(today()) || $zeile['plan']['end'] <= now()->format('H:i')))
                            <form action="{{ route('timesheets.day.plan', [$employe->id, $timesheet->id, $datum]) }}" method="post">
                                @csrf
                                <button type="submit" class="zw-btn-icon is-sm text-violet-600" title="Dienstplanzeit {{ $zeile['plan']['start'] }}–{{ $zeile['plan']['end'] }} übernehmen"><i class="fas fa-calendar-check"></i></button>
                            </form>
                        @endif
                        <a href="{{ route('timesheets.day.create', [$employe->id, $timesheet->id, $datum]) }}" class="zw-btn-icon is-sm text-blue-600" title="Arbeitszeit erfassen"><i class="fas fa-plus"></i></a>
                        <div class="relative" x-data="{ offen: false }" @click.outside="offen = false" @keydown.escape="offen = false">
                            <button type="button" class="zw-btn-icon is-sm" @click="offen = !offen" title="Abwesenheit eintragen" :aria-expanded="offen.toString()"><i class="fas fa-user-clock"></i></button>
                            <div x-show="offen" x-cloak x-transition class="absolute right-0 z-20 mt-1 w-56 max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                                <p class="px-3 py-1.5 text-xs font-semibold text-gray-400 uppercase">Abwesenheit am {{ $tag->format('d.m.') }}</p>
                                @foreach($abwesenheitsGruende as $grund => $prozent)
                                    @continue($grund === 'Urlaub')
                                    <form action="{{ route('timesheets.day.absence', [$employe->id, $timesheet->id, $datum]) }}" method="post">
                                        @csrf
                                        <input type="hidden" name="absence" value="{{ $grund }}">
                                        <button type="submit" class="w-full text-left px-3 py-1.5 text-sm hover:bg-blue-50">{{ $grund }} <span class="text-gray-400">({{ $prozent }} %)</span></button>
                                    </form>
                                @endforeach
                                <p class="px-3 py-1.5 text-[11px] text-gray-400 border-t border-gray-100">Urlaub bitte über die Urlaubsverwaltung beantragen.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    <p class="zw-hint mt-3">
        Soll = Stellenanteil laut Vertrag, verteilt auf die vertraglichen Arbeitstage. Urlaub und Abwesenheiten werden automatisch gutgeschrieben (nur an Arbeitstagen).
        Der Dienstplan wird nur als Vorschlag angezeigt und erst durch „übernehmen“ gebucht.
    </p>
</div>
@endsection
