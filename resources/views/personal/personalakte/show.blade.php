@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $employe->vorname }} {{ $employe->familienname }} – Personalakte
@endsection

@section('title')
    Personalverwaltung
@endsection

@section('content')
<div class="personal-wrapper">

    @include('personal.partials._akte_header', ['active' => 'uebersicht'])

    {{-- Handlungsbedarf --}}
    @foreach($hinweise as $hinweis)
    <div class="rounded-lg p-3 mb-2 text-sm border
        {{ $hinweis['type'] === 'danger' ? 'bg-red-50 text-red-800 border-red-200' : ($hinweis['type'] === 'warning' ? 'bg-yellow-50 text-yellow-800 border-yellow-200' : 'bg-blue-50 text-blue-800 border-blue-200') }}">
        {{ $hinweis['text'] }}
    </div>
    @endforeach
    @if($hinweise->isNotEmpty())<div class="mb-4"></div>@endif

    {{-- Kennzahlen --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="personal-stat">
            <span class="personal-stat-label">Stellenanteil</span>
            <span class="personal-stat-value">{{ $percent }} %</span>
        </div>
        <div class="personal-stat">
            <span class="personal-stat-label">Wochenstunden</span>
            <span class="personal-stat-value">{{ $hours }}</span>
        </div>
        <div class="personal-stat">
            <span class="personal-stat-label">Laufende Anstellungen</span>
            <span class="personal-stat-value">{{ $laufend->count() }}</span>
        </div>
        <div class="personal-stat">
            <span class="personal-stat-label">Beschäftigt seit</span>
            <span class="personal-stat-value text-lg">{{ $firstStart ? \Carbon\Carbon::parse($firstStart)->format('d.m.Y') : '–' }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Stammdaten-Übersicht --}}
        <div class="personal-card lg:col-span-1">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-gray-700">Stammdaten</h2>
                @can('edit employe')
                    <a href="{{ route('personal.personalakte.stammdaten', $employe->id) }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">Bearbeiten →</a>
                @endcan
            </div>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-400 text-xs">Geburtsdatum</dt>
                    <dd class="font-medium text-gray-800">
                        {{ optional($employe->geburtstag)->format('d.m.Y') ?? '–' }}
                        @if($employe->geburtstag)<span class="text-gray-400 font-normal">({{ $employe->geburtstag->age }})</span>@endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Geschlecht</dt>
                    <dd class="font-medium text-gray-800">{{ $employe->employe_data?->geschlecht ?? '–' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Geburtsort</dt>
                    <dd class="font-medium text-gray-800">{{ $employe->employe_data?->geburtsort ?: '–' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Staatsangehörigkeit</dt>
                    <dd class="font-medium text-gray-800">{{ $employe->employe_data?->staatsangehoerigkeit ?: '–' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-400 text-xs">Sozialversicherungsnummer</dt>
                    <dd class="font-medium text-gray-800">{{ $employe->employe_data?->sozialversicherungsnummer ?: '–' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Schwerbehindert</dt>
                    <dd class="font-medium text-gray-800">{{ $employe->employe_data?->schwerbehindert ? 'ja' : 'nein' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Anstellungen (Kurzübersicht) --}}
        <div class="personal-card lg:col-span-2">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <h2 class="text-base font-semibold text-gray-700">Anstellungen</h2>
                <div class="flex gap-4 text-sm">
                    @can('view contracts')
                        <a href="{{ route('personal.contracts.index', $employe->id) }}" class="text-blue-600 hover:text-blue-700 font-medium">Alle Verträge →</a>
                    @endcan
                    @can('createFor', [\App\Models\personal\Employment::class, $employe])
                        <a href="{{ route('personal.contracts.create', $employe->id) }}" class="text-blue-600 hover:text-blue-700 font-medium">+ Neue Anstellung</a>
                    @endcan
                </div>
            </div>
            @if($employments->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="table-personal">
                    <thead>
                        <tr>
                            <th>Bereich</th>
                            <th>Art</th>
                            <th>Stunden</th>
                            <th>Zeitraum</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employments as $employment)
                        <tr>
                            <td class="font-medium text-gray-800">{{ $employment->department->name ?? '–' }}</td>
                            <td>
                                {{ $employment->employment_type?->label() ?? '–' }}
                                @if($employment->contract_type?->isBefristet())<span class="text-xs text-gray-400">(befristet)</span>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $employment->hours ?? '–' }} Std. <span class="text-gray-400">({{ round($employment->percent, 1) }} %)</span></td>
                            <td class="whitespace-nowrap">
                                {{ optional($employment->start)->format('d.m.Y') ?? '–' }}
                                @if($employment->end) – {{ $employment->end->format('d.m.Y') }}@endif
                            </td>
                            <td>
                                @php $zukunft = $employment->start && $employment->start->isFuture(); @endphp
                                <span class="{{ $zukunft ? 'badge-blue' : ($employment->status?->value === 'ruhend' ? 'badge-yellow' : 'badge-green') }}">
                                    {{ $zukunft ? 'ab ' . $employment->start->format('d.m.Y') : ($employment->status?->label() ?? '–') }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="text-sm text-gray-500">Keine laufenden oder künftigen Anstellungen.</p>
            @endif
        </div>
    </div>

    {{-- Laufende Prozesse & Wiedervorlagen --}}
    @if($links->isNotEmpty() || $reminders->isNotEmpty())
    <div class="personal-card mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Prozesse & Wiedervorlagen</h2>
        <ul class="text-sm space-y-1.5">
            @foreach($links as $link)
            <li class="flex items-center gap-2 flex-wrap">
                <span class="badge-gray">{{ $link->type?->label() }}</span>
                @canany(['manage procedures', 'view assigned procedures'])
                    <a href="{{ url('procedure') }}" class="text-blue-600 hover:underline">{{ $link->procedure?->name ?? 'Prozess #' . $link->procedure_id }}</a>
                @else
                    <span>{{ $link->procedure?->name ?? 'Prozess #' . $link->procedure_id }}</span>
                @endcanany
                <span class="text-gray-400">· {{ $link->status?->label() }}</span>
            </li>
            @endforeach
            @foreach($reminders as $reminder)
            <li class="flex items-center gap-2 flex-wrap">
                <span class="{{ $reminder->due_date->isPast() ? 'badge-red' : 'badge-yellow' }}">Wiedervorlage</span>
                <span>{{ $reminder->label() }} am {{ $reminder->due_date->format('d.m.Y') }}</span>
                @if($reminder->note)<span class="text-gray-400">– {{ $reminder->note }}</span>@endif
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Weitere Module zur Person --}}
    @php
        $module = collect([
            ['can' => auth()->user()->canAny(['has holidays', 'approve holidays']), 'url' => fn () => route('holidays.account', $employe->id),
             'icon' => '🏖️', 'farbe' => 'bg-sky-50 text-sky-600', 'titel' => 'Urlaub', 'text' => 'Urlaubskonto, Anspruch und Anträge'],
            ['can' => auth()->user()->can('viewEmploye', [\App\Models\personal\Timesheet::class, $employe]), 'url' => fn () => route('timesheets.show', $employe->id),
             'icon' => '⏱️', 'farbe' => 'bg-indigo-50 text-indigo-600', 'titel' => 'Arbeitszeitnachweis', 'text' => 'Monatsnachweise, Soll/Ist und Überstunden'],
            ['can' => auth()->user()->can('view timesheet anomalies'), 'url' => fn () => route('personal.timesheet-validation.index', $employe->id),
             'icon' => '🛡️', 'farbe' => 'bg-red-50 text-red-600', 'titel' => 'Prüfengine', 'text' => 'Zeiterfassung, Dienstplan & Vertragsänderungen prüfen'],
            ['can' => auth()->user()->can('view trainings'), 'url' => fn () => route('personal.trainings.index'),
             'icon' => '📚', 'farbe' => 'bg-purple-50 text-purple-600', 'titel' => 'Fortbildungen', 'text' => 'Teilnahmen und Katalog'],
            ['can' => auth()->user()->can('manage personal_consents'), 'url' => fn () => route('personal.consents.admin'),
             'icon' => '🔏', 'farbe' => 'bg-rose-50 text-rose-600', 'titel' => 'DSGVO-Einwilligungen', 'text' => 'Übersicht aller Einwilligungen'],
        ])->filter(fn ($m) => $m['can']);
    @endphp
    @if($module->isNotEmpty())
    <h2 class="text-base font-semibold text-gray-700 mb-3">Weitere Module</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @foreach($module as $m)
        <a href="{{ $m['url']() }}" class="personal-card flex items-start gap-4 group">
            <div class="w-10 h-10 rounded-lg {{ $m['farbe'] }} flex items-center justify-center text-xl shrink-0" aria-hidden="true">{{ $m['icon'] }}</div>
            <div>
                <h3 class="font-semibold text-gray-900 group-hover:text-blue-700 transition-colors">{{ $m['titel'] }}</h3>
                <p class="text-gray-500 text-sm mt-0.5">{{ $m['text'] }}</p>
            </div>
        </a>
        @endforeach
    </div>
    @endif

    @include('personal.partials._wirkung')

</div>
@endsection
