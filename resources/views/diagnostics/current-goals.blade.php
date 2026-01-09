@extends('layouts.app')

@push('css')
    @vite(['resources/css/diagnostics.css'])
@endpush

@section('content')
<div class="diagnostic-wrapper">
    <div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center">
                <a href="{{ route('diagnostic.areas', $schueler->id) }}"
                   class="btn btn-outline-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
                <h4 class="mb-0">
                    <i class="fas fa-bullseye"></i> {{ $schueler->name }}
                    <span class="text-muted">- Aktuelle Ziele</span>
                </h4>
            </div>
        </div>
    </div>
    {{-- Info Alert --}}
    <div class="rounded-lg bg-blue-50 p-6 border-l-4 border-blue-400 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Übersicht aktueller Ziele</h3>
                <p class="mt-1 text-sm text-blue-700">
                    Diese Übersicht zeigt alle als "aktuell" markierten Ziele des Schülers aus allen Bereichen.
                    Dies sind die Ziele, an denen momentan aktiv gearbeitet wird.
                </p>
            </div>
        </div>
    </div>

    {{-- Current Goals by Area --}}
    @if($currentGoals->isEmpty())
        <div class="rounded-lg bg-yellow-50 p-6 border-l-4 border-yellow-400">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Keine aktuellen Ziele</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        Keine aktuellen Ziele definiert.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="space-y-6">
            @foreach($currentGoals->groupBy('goal.stage.area.name') as $areaName => $goals)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600">
                        <h2 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                            </svg>
                            {{ $areaName }}
                        </h2>
                    </div>

                    <div class="p-6 space-y-6">
                        @foreach($goals->groupBy('goal.stage.name') as $stageName => $stageGoals)
                            <div>
                                <h3 class="text-md font-semibold text-gray-800 pb-2 border-b-2 border-gray-200 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                    </svg>
                                    {{ $stageName }}
                                </h3>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                                                    Code
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Beschreibung
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                                    Letzte Erfassung
                                                </th>
                                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($stageGoals as $assessment)
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-semibold bg-gray-100 text-gray-800">
                                                            {{ $assessment->goal->code }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">
                                                        {{ $assessment->goal->description }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        {{ $assessment->session->session_date->format('d.m.Y') }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                            </svg>
                                                            Aktuelles Ziel
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                <div class="flex justify-center mb-3">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900">{{ $currentGoals->count() }}</h3>
                <p class="text-sm text-gray-600 mt-1">Aktuelle Ziele gesamt</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                <div class="flex justify-center mb-3">
                    <div class="p-3 bg-green-100 rounded-full">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900">{{ $currentGoals->groupBy('goal.stage.area.name')->count() }}</h3>
                <p class="text-sm text-gray-600 mt-1">Betroffene Bereiche</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
                <div class="flex justify-center mb-3">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900">{{ $currentGoals->groupBy('goal.stage.name')->count() }}</h3>
                <p class="text-sm text-gray-600 mt-1">Betroffene Stufen</p>
            </div>
        </div>
    @endif
</div>
</div>
@endsection

@push('js')
    @vite(['resources/js/diagnostics.js'])
@endpush

