@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('title')
    Personalverwaltung
@endsection

@section('site-title')
    Mitarbeitende
@endsection

@section('content')
@php
    $statusInfo = [
        'aktiv'         => ['Aktiv', 'badge-green'],
        'ruhend'        => ['Ruhend', 'badge-yellow'],
        'kuenftig'      => ['Beginnt künftig', 'badge-blue'],
        'ausgeschieden' => ['Ausgeschieden', 'badge-gray'],
        'ohne'          => ['Ohne Anstellung', 'badge-gray'],
    ];
    $darfAkte = auth()->user()->can('view personal_data');
    $darfVertraege = auth()->user()->can('view contracts');
@endphp
<div class="personal-wrapper" x-data="{ suche: '', status: 'alle' }">

    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mitarbeitende</h1>
            <p class="text-gray-500 text-sm mt-1">Personalakten, Stammdaten und Verträge</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('personal.vorgesetzte.index') }}" class="btn-personal-secondary text-sm">Vorgesetzte & Stellvertretungen</a>
            <a href="{{ route('employes.bulk-holiday-claim') }}" class="btn-personal-secondary text-sm">Urlaubsanspruch für Gruppen</a>
            @can('create employe')
                <a href="{{ route('users.create') }}" class="btn-personal-primary text-sm">+ Mitarbeitende anlegen</a>
            @endcan
        </div>
    </div>

    <div class="personal-card !p-0 overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
            <input type="search" x-model="suche" placeholder="Name, E-Mail oder Bereich suchen …"
                   class="personal-input max-w-sm" aria-label="Mitarbeitende suchen">
            <div class="flex flex-wrap gap-1.5 text-sm" role="group" aria-label="Nach Status filtern">
                @foreach(['alle' => ['Alle', $employes->count()]] + collect($statusInfo)->map(fn ($s, $k) => [$s[0], $counts[$k] ?? 0])->all() as $key => [$label, $anzahl])
                    @continue($key !== 'alle' && $anzahl === 0)
                    <button type="button" @click="status = '{{ $key }}'"
                            class="rounded-full px-3 py-1 border transition-colors"
                            :class="status === '{{ $key }}' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">
                        {{ $label }} <span class="opacity-70">{{ $anzahl }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table-personal">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Bereich(e)</th>
                        <th class="text-right">Stellenanteil</th>
                        <th>Status</th>
                        <th>Nächstes Vertragsende</th>
                        <th class="text-right"><span class="sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employes as $e)
                        @php($suchtext = mb_strtolower($e['familienname'] . ' ' . $e['vorname'] . ' ' . $e['email'] . ' ' . $e['bereiche']))
                        <tr x-show="(status === 'alle' || status === '{{ $e['status'] }}') && (suche === '' || @js($suchtext).includes(suche.toLowerCase()))">
                            <td>
                                <a href="{{ $darfAkte ? route('personal.personalakte.show', $e['id']) : route('personal.personalakte.stammdaten', $e['id']) }}"
                                   class="font-medium text-gray-900 hover:text-blue-700">
                                    {{ $e['familienname'] }}, {{ $e['vorname'] }}
                                </a>
                                <div class="text-xs text-gray-400">{{ $e['email'] }}</div>
                            </td>
                            <td>{{ $e['bereiche'] ?: '–' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $e['prozent'] > 0 ? $e['prozent'] . ' %' : '–' }}</td>
                            <td><span class="{{ $statusInfo[$e['status']][1] }}">{{ $statusInfo[$e['status']][0] }}</span></td>
                            <td class="whitespace-nowrap {{ $e['endeBald'] ? 'text-red-600 font-medium' : '' }}">{{ $e['ende'] ?? '–' }}</td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex gap-1.5">
                                    @if($darfAkte)
                                        <a href="{{ route('personal.personalakte.show', $e['id']) }}" class="btn-personal-secondary !px-2 !py-1 text-xs" title="Personalakte öffnen">Akte</a>
                                    @endif
                                    <a href="{{ route('personal.personalakte.stammdaten', $e['id']) }}" class="btn-personal-secondary !px-2 !py-1 text-xs" title="Stammdaten bearbeiten">Stammdaten</a>
                                    @if($darfVertraege)
                                        <a href="{{ route('personal.contracts.index', $e['id']) }}" class="btn-personal-secondary !px-2 !py-1 text-xs" title="Verträge">Verträge</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-gray-400 py-8">Keine Mitarbeitenden vorhanden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush
