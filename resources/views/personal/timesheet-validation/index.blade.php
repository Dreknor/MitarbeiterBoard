@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('content')
<div class="personal-wrapper">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Prüfengine – Zeiterfassung &amp; Verträge</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $employe->name }} {{ $employe->familienname }} · {{ $month->translatedFormat('F Y') }}</p>
        </div>
        <div class="flex gap-3 items-center">
            <a href="{{ route('personal.timesheet-validation.index', ['employe' => $employe->id, 'date' => $month->copy()->subMonth()->format('Y-m')]) }}"
               class="btn-personal-secondary text-sm">&larr;</a>
            <a href="{{ route('personal.timesheet-validation.index', ['employe' => $employe->id, 'date' => $month->copy()->addMonth()->format('Y-m')]) }}"
               class="btn-personal-secondary text-sm">&rarr;</a>

            @can('run timesheet validation')
            <form method="POST" action="{{ route('personal.timesheet-validation.run', ['employe' => $employe->id, 'date' => $month->format('Y-m')]) }}">
                @csrf
                <button type="submit" class="btn-personal-primary text-sm">🔄 Neu prüfen</button>
            </form>
            @endcan

            <a href="{{ route('personal.personalakte.show', $employe->id) }}" class="btn-personal-secondary text-sm">← Zurück zur Akte</a>
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : (session('type') === 'warning' ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : 'bg-red-50 text-red-800 border border-red-200') }}">
        {{ session('Meldung') }}
    </div>
    @endif

    @if($timesheet?->requires_review)
    <div class="alert-warning flex items-center gap-2 mb-4">
        <span class="text-lg">⚠️</span>
        <span><strong>Erneute Prüfung erforderlich:</strong> {{ $timesheet->review_reason }}</span>
    </div>
    @endif

    {{-- Vertragsänderungs-Banner (Aufgabe 5.2) --}}
    @foreach($contractChangeAnomalies as $anomaly)
        @include('personal.timesheet-validation._contract_change_banner', ['anomaly' => $anomaly])
    @endforeach

    {{-- Monatssaldo (Formel: Ist + Urlaub/Krank-Gutschrift − Soll) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="personal-stat">
            <span class="personal-stat-value">{{ convertTime($balance['ist_seconds']) }}</span>
            <span class="personal-stat-label">Ist-Arbeitszeit</span>
        </div>
        <div class="personal-stat">
            <span class="personal-stat-value">{{ convertTime($balance['credit_seconds']) }}</span>
            <span class="personal-stat-label">Urlaub/Krank-Gutschrift</span>
        </div>
        <div class="personal-stat">
            <span class="personal-stat-value">{{ convertTime($balance['soll_seconds']) }}</span>
            <span class="personal-stat-label">Soll-Zeit (Vertragshistorie)</span>
        </div>
        <div class="personal-stat">
            <span class="personal-stat-value {{ $balance['balance_seconds'] < 0 ? 'text-red-600' : 'text-green-700' }}">
                {{ convertTime($balance['balance_seconds']) }}
            </span>
            <span class="personal-stat-label">Monatssaldo</span>
        </div>
    </div>

    {{-- Detailansicht: Tagesgenaue Farbcodierung (Aufgabe 5.1 / 5.3) --}}
    <div class="personal-card mb-6 overflow-x-auto">
        <h2 class="font-semibold text-gray-900 mb-3">Monatsübersicht</h2>
        <table class="table-personal">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Status</th>
                    <th>Auffälligkeiten</th>
                </tr>
            </thead>
            <tbody>
                @for($day = $month->copy()->startOfMonth(); $day->lessThanOrEqualTo($month->copy()->endOfMonth()); $day->addDay())
                    @php
                        $dayAnomalies = $anomaliesByDay->get($day->format('Y-m-d'), collect());
                        $maxSeverity = $dayAnomalies->sortByDesc(fn($a) => $a->severity->weight())->first()?->severity;
                    @endphp
                    <tr class="{{ $maxSeverity?->dayClasses() }}">
                        <td class="font-medium">
                            {{ $day->format('d.m.Y') }}
                            <span class="text-gray-400 text-xs">({{ $day->translatedFormat('D') }})</span>
                        </td>
                        <td>
                            @if($dayAnomalies->isEmpty())
                                <span class="badge-green">ok</span>
                            @else
                                <span class="{{ 'inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full ' . $maxSeverity->badgeClasses() }}">
                                    {{ $maxSeverity->label() }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @foreach($dayAnomalies as $a)
                                <div class="text-xs text-gray-700 {{ $a->is_resolved ? 'line-through text-gray-400' : '' }}">
                                    {{ $a->rule_type->label() }}: {{ $a->description }}
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    {{-- Offene Auffälligkeiten (Aufgabe 5.3) --}}
    <div class="personal-card">
        <h2 class="font-semibold text-gray-900 mb-3">Auffälligkeiten &amp; Warnungen</h2>
        @forelse($anomalies as $anomaly)
            <div class="flex items-start justify-between gap-4 py-3 border-b border-gray-100 last:border-0">
                <div>
                    <span class="{{ 'inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full mr-2 ' . $anomaly->severity->badgeClasses() }}">
                        {{ $anomaly->severity->label() }}
                    </span>
                    <span class="text-sm font-medium text-gray-800">{{ $anomaly->rule_type->label() }}</span>
                    @if($anomaly->date)
                        <span class="text-xs text-gray-400">({{ $anomaly->date->format('d.m.Y') }})</span>
                    @endif
                    <p class="text-sm text-gray-600 mt-1 {{ $anomaly->is_resolved ? 'line-through text-gray-400' : '' }}">{{ $anomaly->description }}</p>
                    @if($anomaly->is_resolved)
                        <p class="text-xs text-green-700 mt-1">✓ Quittiert von {{ $anomaly->resolvedBy?->name }} am {{ $anomaly->resolved_at->format('d.m.Y H:i') }}</p>
                    @endif
                </div>
                @can('resolve timesheet anomalies')
                    @if(!$anomaly->is_resolved)
                    <form method="POST" action="{{ route('personal.timesheet-validation.resolve', $anomaly->id) }}" class="shrink-0">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-personal-secondary text-xs">Quittieren</button>
                    </form>
                    @endif
                @endcan
            </div>
        @empty
            <p class="text-gray-400 text-sm py-6 text-center">Keine Auffälligkeiten für diesen Zeitraum.</p>
        @endforelse
    </div>

</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush

