@extends('layouts.app')

@push('css')
    @vite(['resources/css/diagnostics.css'])
    <style>
        body, .main-panel, .content {
            background-color: #f8f9fa !important;
        }
        .diagnostic-wrapper {
            background: white;
            min-height: 100vh;
        }
    </style>
@endpush

@section('content')
<div class="diagnostic-wrapper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Header Section -->
    <div class="mb-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('diagnostic.index') }}"
               class="btn-diagnostic-secondary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Zurück
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <span class="inline-flex items-center px-4 py-2 rounded-lg text-lg font-bold shadow-sm"
                          style="background-color: {{ $klasse->color }}; color: {{ $klasse->text_color }}">
                        {{ $klasse->name }}
                    </span>
                    <span class="text-gray-600">Schüler wählen</span>
                </h1>
            </div>
        </div>
    </div>
    @if($schueler->isEmpty())
        <div class="alert-diagnostic alert-warning">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Keine Schüler vorhanden</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        Keine Schüler in dieser Klasse vorhanden.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="card-diagnostic">
            <div class="card-diagnostic-header">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    Schülerliste
                    <span class="ml-2 text-sm font-normal text-gray-500">({{ $schueler->count() }} {{ $schueler->count() == 1 ? 'Schüler' : 'Schüler' }})</span>
                </h2>
            </div>
            <div class="overflow-x-auto">
                <div class="min-w-full">
                    <table class="table-diagnostic min-w-full">
                    <thead>
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Name
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Vorname
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Aktuelle Ziele
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Letzte Erfassung
                            </th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Aktionen
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($schueler as $s)
                            @php
                                $data = $schuelerData[$s->id] ?? [];
                                $currentGoals = $data['current_goals'] ?? collect();
                                $lastSession = $data['last_session'] ?? null;
                                $openSessionsCount = $data['open_sessions_count'] ?? 0;
                            @endphp
                            <tr class="hover:bg-blue-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $s->nachname }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-700">{{ $s->vorname }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($openSessionsCount > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $openSessionsCount }} offen
                                        </span>
                                    @elseif($lastSession)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Erfasst
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            Noch keine Erfassung
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($currentGoals->isNotEmpty())
                                        <div class="space-y-1">
                                            @foreach($currentGoals->take(3) as $goal)
                                                @php
                                                    // Farbpalette für verschiedene Bereiche
                                                    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];
                                                    $areaColor = $colors[($goal->area_id - 1) % count($colors)];
                                                @endphp
                                                <div class="flex items-start text-xs">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mr-2 flex-shrink-0"
                                                          style="background-color: {{ $areaColor }}20; color: {{ $areaColor }}; border: 1px solid {{ $areaColor }}40;">
                                                        {{ $goal->code }}
                                                    </span>
                                                    <span class="text-gray-700 line-clamp-1">{{$goal->description}}</span>
                                                </div>
                                            @endforeach
                                            @if($currentGoals->count() > 3)
                                                <div class="text-xs text-gray-500 italic">
                                                    + {{ $currentGoals->count() - 3 }} weitere {{ $currentGoals->count() - 3 === 1 ? 'Ziel' : 'Ziele' }}
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400 italic">Keine aktuellen Ziele</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($lastSession)
                                        <div class="text-sm text-gray-700">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                {{ $lastSession->completed_at->format('d.m.Y') }}
                                            </div>
                                            <div class="text-xs text-gray-500 ml-5">
                                                {{ $lastSession->completed_at->diffForHumans() }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('diagnostic.areas', $s->id) }}"
                                       class="btn-diagnostic-primary text-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                        </svg>
                                        Diagnose starten
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    @endif
    </div>
</div>
@endsection

@push('js')
    @vite(['resources/js/diagnostics.js'])
@endpush

