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
    <div class="max-w-[1920px] mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-4 md:py-6">
    <!-- Header Section -->
    <div class="mb-4 md:mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 md:gap-4">
            <a href="{{ route('diagnostic.students', $schueler->klasse_id) }}"
               class="btn-diagnostic-secondary shrink-0">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Zurück
            </a>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 flex flex-wrap items-center gap-2 md:gap-3">
                    <svg class="w-6 h-6 md:w-7 md:h-7 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span class="break-all">{{ $schueler->name }}</span>
                    <span class="text-gray-600 text-lg md:text-xl whitespace-nowrap">- Bereich wählen</span>
                </h1>
            </div>
        </div>
    </div>
    @if($areas->isEmpty())
        <div class="rounded-lg bg-yellow-50 p-6 border-l-4 border-yellow-400">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Keine Diagnosebereiche vorhanden</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        Keine Diagnosebereiche vorhanden. Bitte wenden Sie sich an einen Administrator.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5 lg:gap-6">
            @foreach($areas as $area)
                @php
                    $status = $areaStatus[$area->id];
                @endphp
                <div class="diagnostic-card bg-white rounded-xl shadow-sm border-2 overflow-hidden {{ $status['has_open_session'] ? 'border-yellow-400' : 'border-gray-200' }}">
                    <div class="px-4 py-3 md:px-5 md:py-3.5 {{ $status['has_open_session'] ? 'bg-gradient-to-r from-yellow-400 to-yellow-500 text-white' : 'bg-gradient-to-r from-blue-500 to-purple-600 text-white' }}">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base md:text-lg font-semibold truncate pr-2">{{ $area->name }}</h3>
                            @if($status['has_open_session'])
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white bg-opacity-30">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    Offen
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="p-3 md:p-4 lg:p-5 space-y-3 md:space-y-3.5">
                        @if($area->description)
                            <div class="area-goal-card p-3 rounded-lg">
                                <p class="text-xs font-semibold text-purple-700 uppercase tracking-wide mb-1">Bereichsziel</p>
                                <p class="text-xs md:text-sm text-gray-700 leading-snug">{{ $area->description }}</p>
                            </div>
                        @endif

                        {{-- Aktuelle Ziele des Schülers in diesem Bereich --}}
                        @if($status['current_goals']->count() > 0)
                            <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-3">
                                <div class="flex items-center mb-2">
                                    <svg class="w-4 h-4 mr-1.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                    <p class="text-xs md:text-sm font-bold text-blue-900">
                                        Aktuelle Ziele ({{ $status['current_goals']->count() }})
                                    </p>
                                </div>
                                <div class="space-y-1.5">
                                    @foreach($status['current_goals'] as $goal)
                                        <div class="bg-white rounded-md p-2 md:p-2.5 border border-blue-200 shadow-sm">
                                            <div class="flex items-start gap-2">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800 shrink-0">
                                                    {{ $goal->code }}
                                                </span>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs text-gray-500 mb-0.5">{{ $goal->stage_name }}</p>
                                                    <p class="text-xs md:text-sm text-gray-800 leading-snug">{{ $goal->description }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center text-xs md:text-sm text-gray-600">
                            <svg class="w-4 h-4 md:w-5 md:h-5 mr-1.5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-medium">{{ $status['completed_count'] }}</span>
                            <span class="ml-1">abgeschlossene {{ $status['completed_count'] == 1 ? 'Diagnose' : 'Diagnosen' }}</span>
                        </div>

                        @if($status['has_open_session'])
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-2.5 rounded-r-lg">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-4 w-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-2">
                                        <p class="text-xs font-medium text-yellow-800">Offene Session</p>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('diagnostic.session', $status['open_session']->id) }}"
                               class="block w-full text-center px-3 py-2 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white text-xs md:text-sm font-medium rounded-lg hover:from-yellow-600 hover:to-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-all shadow-sm hover:shadow-md">
                                <span class="flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Fortsetzen
                                </span>
                            </a>
                        @else
                            <form action="{{ route('diagnostic.start', [$schueler->id, $area->id]) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="w-full px-3 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-xs md:text-sm font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all shadow-sm hover:shadow-md">
                                    <span class="flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Neue Diagnose starten
                                    </span>
                                </button>
                            </form>
                        @endif

                        @if($status['completed_count'] > 0)
                            <a href="{{ route('diagnostic.history', [$schueler->id, $area->id]) }}"
                               class="block w-full text-center px-3 py-2 border border-cyan-600 text-cyan-700 text-xs md:text-sm font-medium rounded-lg hover:bg-cyan-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 transition-colors">
                                <span class="flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Verlauf anzeigen
                                </span>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    </div>
</div>
@endsection

@push('js')
    @vite(['resources/js/diagnostics.js'])
@endpush

