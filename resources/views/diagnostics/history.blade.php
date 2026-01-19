@extends('layouts.app')

@push('css')
    @vite(['resources/css/diagnostics.css'])
@endpush

@section('content')
<div class="diagnostic-wrapper">
    <div class="max-w-[1920px] mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-4 md:py-6">
        <!-- Header Section -->
        <div class="mb-4 md:mb-6">
            <div class="flex flex-col gap-3 md:gap-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 md:gap-4">
                    <a href="{{ route('diagnostic.areas', $schueler->id) }}"
                       class="btn-diagnostic-secondary shrink-0">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Zurück
                    </a>
                    <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900 flex flex-wrap items-center gap-2 md:gap-3">
                            <svg class="w-6 h-6 md:w-7 md:h-7 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="break-all">{{ $schueler->name }}</span>
                            <span class="text-gray-400">-</span>
                            <span class="break-all">{{ $area->name }}</span>
                            <span class="text-gray-600 text-lg md:text-xl whitespace-nowrap">- Verlauf</span>
                        </h1>
                        @if($sessions->isNotEmpty())
                            <a href="{{ route('diagnostic.export-area-pdf', [$schueler->id, $area->id]) }}"
                               target="_blank"
                               class="btn-diagnostic-primary shrink-0">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Verlauf als PDF exportieren
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Sessions Overview --}}
        <div class="diagnostic-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 md:px-5 md:py-3.5 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-purple-600">
                <h2 class="text-base md:text-lg font-semibold text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Durchgeführte Erfassungen
                    <span class="ml-2 text-sm font-normal text-blue-100">({{ $sessions->count() }})</span>
                </h2>
            </div>

            <div class="p-4 md:p-5 lg:p-6">
                @if($sessions->isEmpty())
                    <div class="text-center py-12 md:py-16">
                        <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full bg-gray-100 mb-4">
                            <svg class="w-8 h-8 md:w-10 md:h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2">Noch keine Erfassungen</h3>
                        <p class="text-sm md:text-base text-gray-500 max-w-md mx-auto">
                            Es wurden noch keine Erfassungen für diesen Bereich durchgeführt.
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto diagnostic-table-wrapper">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                <tr>
                                    <th class="px-4 py-3 md:px-5 md:py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Datum
                                    </th>
                                    <th class="px-4 py-3 md:px-5 md:py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 md:px-5 md:py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Ersteller
                                    </th>
                                    <th class="px-4 py-3 md:px-5 md:py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Bewertete Ziele
                                    </th>
                                    <th class="px-4 py-3 md:px-5 md:py-3.5 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($sessions as $session)
                                    <tr class="hover:bg-blue-50/50 transition-colors duration-150">
                                        <td class="px-4 py-3 md:px-5 md:py-3.5 whitespace-nowrap">
                                            <div class="flex items-center text-sm font-semibold text-gray-900">
                                                <svg class="w-4 h-4 mr-2 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                {{ $session->session_date->format('d.m.Y') }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 md:px-5 md:py-3.5 whitespace-nowrap">
                                            @if($session->is_completed)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-200">
                                                    <svg class="w-3.5 h-3.5 mr-1 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    Abgeschlossen
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                    <svg class="w-3.5 h-3.5 mr-1 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    In Bearbeitung
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 md:px-5 md:py-3.5 whitespace-nowrap">
                                            <div class="flex items-center text-sm text-gray-700">
                                                <svg class="w-4 h-4 mr-2 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                {{ $session->user->name }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 md:px-5 md:py-3.5 whitespace-nowrap">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <svg class="w-4 h-4 mr-2 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                </svg>
                                                <span class="font-medium text-gray-900">{{ $session->assessments->count() }}</span>
                                                <span class="ml-1">{{ $session->assessments->count() == 1 ? 'Ziel' : 'Ziele' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 md:px-5 md:py-3.5 whitespace-nowrap text-right">
                                            <a href="{{ route('diagnostic.session', $session->id) }}"
                                               class="inline-flex items-center px-3 py-1.5 md:px-3.5 md:py-2 border border-transparent text-xs md:text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all shadow-sm hover:shadow-md">
                                                <svg class="w-4 h-4 mr-1.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                Ansehen
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Progress Chart Placeholder --}}
        @if($sessions->count() > 1)
            <div class="mt-4 md:mt-6 diagnostic-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 md:px-5 md:py-3.5 border-b border-gray-200 bg-gradient-to-r from-purple-500 to-pink-600">
                    <h2 class="text-base md:text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Fortschrittsübersicht
                    </h2>
                </div>

                <div class="p-4 md:p-5 lg:p-6">
                    <div class="text-center py-12 md:py-16">
                        <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full bg-purple-50 mb-4">
                            <svg class="w-8 h-8 md:w-10 md:h-10 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2">Grafische Auswertung in Planung</h3>
                        <p class="text-sm md:text-base text-gray-500 max-w-md mx-auto">
                            Eine grafische Darstellung des Lernfortschritts wird in einer zukünftigen Version verfügbar sein.
                        </p>
                        {{-- Hier kommt später Chart.js --}}
                        {{-- <canvas id="progressChart"></canvas> --}}
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

