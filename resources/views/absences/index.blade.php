@extends('layouts.app')

@section('title')
    Abwesenheiten
@endsection

@section('site-title')
    Abwesenheiten
@endsection

@push('css')
    @vite('resources/css/absences.css')
@endpush

@php
    $heute = \Illuminate\Support\Carbon::today();
    $filterAktiv = $filter['q'] !== '' || $filter['grund'] !== '' || $filter['person'] || $filter['von'] || $filter['bis'];
    $mitFilter = fn (array $aenderung) => route('absences.index', array_filter(
        array_merge($filter, $aenderung),
        fn ($wert) => $wert !== null && $wert !== ''
    ));
    $status = function (\App\Models\Absence $a) use ($heute): array {
        if ($a->end->lt($heute)) {
            return ['beendet', 'abw-badge-gray'];
        }
        if ($a->start->gt($heute)) {
            $tage = (int) $heute->diffInDays($a->start);

            return [$tage === 1 ? 'ab morgen' : 'in '.$tage.' Tagen', 'abw-badge-blue'];
        }

        return [$a->end->isSameDay($heute) ? 'bis heute' : 'läuft', 'abw-badge-red'];
    };
    $zeitraum = fn (\App\Models\Absence $a) => $a->start->format('d.m.Y').($a->end->gt($a->start) ? ' – '.$a->end->format('d.m.Y') : '');
    $initialen = fn (string $name) => collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($t) => mb_substr($t, 0, 1))->take(2)->implode('');
@endphp

@section('content')
<div class="abw-wrapper"
     x-data="{ offen: @js($errors->any()), start: @js(old('start', $heute->toDateString())), ende: @js(old('end', $heute->toDateString())) }">

    {{-- Kopf --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="abw-page-title">Abwesenheiten</h1>
            <p class="abw-page-sub">Wer fehlt heute, wer in den nächsten Tagen – und Abwesenheiten direkt eintragen.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2" data-tour="abw-aktionen">
            <button type="button" class="abw-btn abw-btn-ghost" data-tour-start="abwesenheiten" title="Kurze Einführung starten">
                <i class="fas fa-route"></i><span>Tour</span>
            </button>
            <a href="{{ route('benachrichtigungen.einstellungen') }}" class="abw-btn abw-btn-secondary"
               title="Abwesenheiten per Benachrichtigung oder Tagesübersicht erhalten">
                <i class="fas fa-bell"></i><span class="hidden sm:inline">Benachrichtigungen</span>
            </a>
            @can('export absence')
                <a href="{{ route('absences.export', request()->query()) }}" class="abw-btn abw-btn-secondary"
                   title="Aktuelle Auswahl als Excel-Datei herunterladen">
                    <i class="fas fa-file-excel"></i><span class="hidden sm:inline">Export</span>
                </a>
            @endcan
            <button type="button" class="abw-btn abw-btn-primary" data-tour="abw-neu" @click="offen = !offen" :aria-expanded="offen">
                <i class="fas" :class="offen ? 'fa-times' : 'fa-plus'"></i>
                <span x-text="offen ? 'Schließen' : 'Abwesenheit eintragen'">Abwesenheit eintragen</span>
            </button>
        </div>
    </div>

    {{-- Eintragen --}}
    <div x-show="offen" x-cloak x-transition.opacity class="abw-card mb-5">
        <div class="abw-card-head">
            <h2 class="abw-card-title"><i class="fas fa-user-clock"></i> Neue Abwesenheit</h2>
        </div>
        @if($errors->any())
            <div class="px-4 pt-4 sm:px-5">
                <div class="abw-alert abw-alert-danger">
                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                    <span>Bitte die markierten Felder prüfen.</span>
                </div>
            </div>
        @endif
        @include('absences.partials.formular')
    </div>

    {{-- Kennzahlen --}}
    <div class="grid gap-3 grid-cols-1 sm:grid-cols-3 mb-5" data-tour="abw-kennzahlen">
        <a href="{{ route('absences.index', ['zeitraum' => 'heute']) }}" class="abw-stat">
            <span class="abw-stat-icon bg-red-50 text-red-600"><i class="fas fa-user-slash"></i></span>
            <span>
                <span class="abw-stat-value block">{{ $kennzahlen['heute'] }}</span>
                <span class="abw-stat-label block">{{ $kennzahlen['heute'] === 1 ? 'Person' : 'Personen' }} heute abwesend</span>
            </span>
        </a>
        <a href="{{ route('absences.index', ['zeitraum' => 'aktuell', 'bis' => $heute->copy()->addDays(7)->toDateString()]) }}" class="abw-stat">
            <span class="abw-stat-icon bg-amber-50 text-amber-600"><i class="fas fa-calendar-week"></i></span>
            <span>
                <span class="abw-stat-value block">{{ $kennzahlen['woche'] }}</span>
                <span class="abw-stat-label block">{{ $kennzahlen['woche'] === 1 ? 'Person' : 'Personen' }} in den nächsten 7 Tagen</span>
            </span>
        </a>
        <a href="{{ route('absences.index', ['zeitraum' => 'geplant']) }}" class="abw-stat">
            <span class="abw-stat-icon bg-blue-50 text-blue-600"><i class="fas fa-calendar-plus"></i></span>
            <span>
                <span class="abw-stat-value block">{{ $kennzahlen['geplant'] }}</span>
                <span class="abw-stat-label block">bereits gemeldete künftige Abwesenheiten</span>
            </span>
        </a>
    </div>

    {{-- Liste --}}
    <div class="abw-card">
        <div class="abw-card-head">
            <nav class="abw-segment" aria-label="Zeitraum" data-tour="abw-zeitraum">
                @foreach($zeitraeume as $key => $label)
                    <a href="{{ $mitFilter(['zeitraum' => $key, 'page' => null]) }}" @class(['is-active' => $filter['zeitraum'] === $key])>{{ $label }}</a>
                @endforeach
            </nav>
            <span class="text-xs text-gray-500">
                {{ $absences->total() }} {{ $absences->total() === 1 ? 'Eintrag' : 'Einträge' }}
            </span>
        </div>

        {{-- Filter --}}
        <form method="get" action="{{ route('absences.index') }}" class="abw-card-body border-b border-gray-100 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end"
              data-tour="abw-filter">
            <input type="hidden" name="zeitraum" value="{{ $filter['zeitraum'] }}">
            <div class="sm:col-span-2">
                <label for="abw-q" class="abw-label">Suche</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input id="abw-q" type="search" name="q" value="{{ $filter['q'] }}" placeholder="Name oder Grund" class="abw-input pl-8">
                </div>
            </div>
            <div>
                <label for="abw-f-grund" class="abw-label">Grund</label>
                <select id="abw-f-grund" name="grund" class="abw-select" onchange="this.form.requestSubmit()">
                    <option value="">alle</option>
                    @foreach($gruende as $grund)
                        <option value="{{ $grund }}" @selected($filter['grund'] === $grund)>{{ $grund }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="abw-f-person" class="abw-label">Person</label>
                <select id="abw-f-person" name="person" class="abw-select" onchange="this.form.requestSubmit()">
                    <option value="">alle</option>
                    @foreach($personen as $person)
                        <option value="{{ $person->id }}" @selected($filter['person'] === $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-1">
                <div>
                    <label for="abw-f-von" class="abw-label">ab</label>
                    <input id="abw-f-von" type="date" name="von" value="{{ $filter['von'] }}" class="abw-input px-2">
                </div>
                <div>
                    <label for="abw-f-bis" class="abw-label">bis</label>
                    <input id="abw-f-bis" type="date" name="bis" value="{{ $filter['bis'] }}" class="abw-input px-2">
                </div>
            </div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                <button type="submit" class="abw-btn abw-btn-secondary flex-1"><i class="fas fa-filter"></i><span>Filtern</span></button>
                @if($filterAktiv)
                    <a href="{{ route('absences.index', ['zeitraum' => $filter['zeitraum']]) }}" class="abw-btn abw-btn-ghost" title="Filter zurücksetzen">
                        <i class="fas fa-undo"></i>
                    </a>
                @endif
            </div>
        </form>

        <div data-tour="abw-liste">
            @if($absences->isEmpty())
                <div class="abw-empty">
                    <i class="fas fa-user-check"></i>
                    <span>{{ $filterAktiv || $filter['zeitraum'] !== 'aktuell' ? 'Keine Abwesenheiten für diese Auswahl.' : 'Aktuell ist niemand abwesend gemeldet.' }}</span>
                </div>
            @else
                {{-- Tabelle ab md --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="abw-table">
                        <thead>
                            <tr>
                                <th>Person</th>
                                <th>Zeitraum</th>
                                <th class="text-right">Arbeitstage</th>
                                <th>Grund</th>
                                <th>Hinweise</th>
                                <th class="text-right"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($absences as $absence)
                                @php [$statusText, $statusKlasse] = $status($absence); @endphp
                                <tr @class(['is-heute' => $statusKlasse === 'abw-badge-red'])>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="abw-avatar">{{ $initialen($absence->user->name) }}</span>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-gray-900">{{ $absence->user->name }}</div>
                                                @if($absence->creator_id && $absence->creator_id !== $absence->users_id)
                                                    <div class="text-xs text-gray-500">eingetragen von {{ $absence->creator->name }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <div>{{ $zeitraum($absence) }}</div>
                                        <span class="abw-badge {{ $statusKlasse }} mt-1">{{ $statusText }}</span>
                                    </td>
                                    <td class="text-right tabular-nums">{{ $absence->days }}</td>
                                    <td>{{ $absence->reason }}</td>
                                    <td>
                                        @include('absences.partials.hinweise')
                                    </td>
                                    <td class="text-right">
                                        @include('absences.partials.loeschen')
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Karten unter md --}}
                <ul class="md:hidden abw-list">
                    @foreach($absences as $absence)
                        @php [$statusText, $statusKlasse] = $status($absence); @endphp
                        <li @class(['abw-item', 'is-heute' => $statusKlasse === 'abw-badge-red'])>
                            <span class="abw-avatar">{{ $initialen($absence->user->name) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-semibold text-gray-900">{{ $absence->user->name }}</div>
                                    <span class="abw-badge {{ $statusKlasse }}">{{ $statusText }}</span>
                                </div>
                                <div class="text-sm text-gray-700">{{ $absence->reason }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $zeitraum($absence) }} · {{ $absence->days }} {{ $absence->days === 1 ? 'Arbeitstag' : 'Arbeitstage' }}
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                                    <div>@include('absences.partials.hinweise')</div>
                                    @include('absences.partials.loeschen')
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($absences->hasPages())
            <div class="flex items-center justify-between gap-2 px-4 py-3 sm:px-5 border-t border-gray-100">
                @if($absences->onFirstPage())
                    <span class="abw-btn abw-btn-secondary abw-btn-sm opacity-50"><i class="fas fa-chevron-left"></i><span class="hidden sm:inline">Zurück</span></span>
                @else
                    <a href="{{ $absences->previousPageUrl() }}" class="abw-btn abw-btn-secondary abw-btn-sm"><i class="fas fa-chevron-left"></i><span class="hidden sm:inline">Zurück</span></a>
                @endif
                <span class="text-xs text-gray-500">Seite {{ $absences->currentPage() }} von {{ $absences->lastPage() }}</span>
                @if($absences->hasMorePages())
                    <a href="{{ $absences->nextPageUrl() }}" class="abw-btn abw-btn-secondary abw-btn-sm"><span class="hidden sm:inline">Weiter</span><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="abw-btn abw-btn-secondary abw-btn-sm opacity-50"><span class="hidden sm:inline">Weiter</span><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>
</div>

<x-tour id="abwesenheiten" :version="1" :steps="[
    [
        'target' => 'abw-neu',
        'title' => 'Neu: direkt hier eintragen',
        'text' => 'Abwesenheiten trägst du jetzt auch auf dieser Seite ein – für dich selbst oder, mit dem entsprechenden Recht bzw. als Vorgesetzte:r, für andere. Schließt eine Meldung direkt an eine gleiche an, wird die bestehende verlängert.',
    ],
    [
        'target' => 'abw-kennzahlen',
        'title' => 'Auf einen Blick',
        'text' => 'Wer ist heute weg, wer in den nächsten sieben Tagen, und was ist schon für später gemeldet? Ein Klick auf eine Kachel filtert die Liste passend.',
    ],
    [
        'target' => 'abw-zeitraum',
        'title' => 'Aktuelles zuerst',
        'text' => 'Die Liste startet mit aktuellen und geplanten Abwesenheiten, nach Beginn sortiert. Nur heutige, nur künftige, vergangene oder alle Einträge erreichst du über diese Umschalter.',
    ],
    [
        'target' => 'abw-filter',
        'title' => 'Suchen und filtern',
        'text' => 'Nach Name, Grund, Person oder Zeitraum filtern. Die Liste lädt seitenweise und bleibt dadurch auch bei vielen Einträgen schnell.',
    ],
    [
        'target' => 'abw-liste',
        'title' => 'Mehr Details je Eintrag',
        'text' => 'Du siehst den Status (läuft, ab morgen …), die Zahl der Arbeitstage ohne Wochenenden und Feiertage, ob die Abwesenheit auf dem Vertretungsplan steht oder aus einem Urlaub stammt – und wer sie eingetragen hat.',
    ],
    [
        'target' => 'abw-loeschen',
        'title' => 'Löschen mit Rückfrage',
        'text' => 'Gelöscht wird erst nach einer Rückfrage. Abgelaufene Abwesenheiten bleiben als Nachweis erhalten, Abwesenheiten aus einem Urlaub werden über den Urlaub storniert.',
    ],
    [
        'target' => 'abw-aktionen',
        'title' => 'Benachrichtigung und Export',
        'text' => auth()->user()->can('export absence')
            ? 'Hier stellst du ein, ob du neue Abwesenheiten per Push, Mail oder Tagesübersicht bekommst. Der Excel-Export übernimmt jetzt die aktuelle Auswahl.'
            : 'Hier stellst du ein, ob du neue Abwesenheiten per Push, Mail oder Tagesübersicht bekommst. Die Tour kannst du jederzeit über „Tour“ erneut starten.',
    ],
]" />
@endsection
