@extends('layouts.app')

@push('css')
    @vite(['resources/css/diagnostics.css'])
@endpush

@section('content')
<div class="diagnostic-wrapper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        Diagnosebögen
                    </h1>
                    <p class="mt-2 text-sm text-gray-600">Wählen Sie eine Klasse aus, um mit der Diagnose zu beginnen</p>
                </div>
                @can('manage diagnostics')
                    <a href="{{ route('diagnostic.admin.index') }}"
                       class="btn-diagnostic-primary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Verwaltung
                    </a>
                @endcan
            </div>
        </div>

        @if($klassen->isEmpty())
            <div class="alert-diagnostic alert-warning">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Keine Klassen zugewiesen</h3>
                        <p class="mt-1 text-sm text-yellow-700">
                            Sie haben keine Klassen für Diagnosebögen zugewiesen.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <!-- Classes Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($klassen as $klasse)
                    <div class="card-diagnostic group cursor-pointer"
                         onclick="window.location='{{ route('diagnostic.students', $klasse->id) }}'">
                        <div class="card-diagnostic-body">
                            <!-- Class Badge -->
                            <div class="flex items-center justify-between mb-4">
                                <span class="inline-flex items-center px-4 py-2 rounded-lg text-lg font-bold shadow-sm"
                                      style="background-color: {{ $klasse->color }}; color: {{ $klasse->text_color }}">
                                    {{ $klasse->name }}
                                </span>
                                <svg class="w-10 h-10 text-gray-200 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>

                            <!-- Student Count -->
                            <div class="flex items-center text-gray-600 mb-4">
                                <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <span class="font-semibold text-gray-900">{{ $klasse->schueler_count }}</span>
                                <span class="ml-1 text-sm">{{ $klasse->schueler_count == 1 ? 'Schüler' : 'Schüler' }}</span>
                            </div>

                            <!-- Action Button -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                <span class="text-sm text-gray-500 group-hover:text-blue-600 transition-colors">
                                    Zur Klasse
                                </span>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </div>
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

