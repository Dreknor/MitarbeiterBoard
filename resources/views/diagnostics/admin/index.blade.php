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
<div class="diagnostic-wrapper" x-data="diagnosticAdmin">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Diagnosebögen - Verwaltung
                </h1>
                <div class="flex gap-2">
                    <a href="{{ route('diagnostic.index') }}" class="btn-diagnostic-secondary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Zurück zur Übersicht
                    </a>
                    <button type="button" class="btn-diagnostic-primary" @click="openAreaForm()">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Neuer Bereich
                    </button>
                </div>
            </div>
        </div>

        {{-- Inline Area Form --}}
        <div x-show="showAreaForm"
             x-collapse
             id="area-form"
             class="mb-6 bg-white rounded-lg shadow-sm border border-blue-300 overflow-hidden">
            <div class="bg-blue-50 border-b border-blue-200 px-6 py-3">
                <h3 class="text-lg font-semibold text-gray-900" x-text="areaForm.id ? 'Bereich bearbeiten' : 'Neuer Bereich'"></h3>
            </div>
            <form @submit.prevent="saveArea()" class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                        <input type="text"
                               x-model="areaForm.name"
                               placeholder="z.B. Verhaltenssteuerung"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                        <textarea x-model="areaForm.description"
                                  rows="3"
                                  placeholder="Optionale Beschreibung des Diagnosebereichs"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox"
                               x-model="areaForm.active"
                               id="area-active"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="area-active" class="ml-2 block text-sm text-gray-700">
                            Aktiv (für Erfassungen verfügbar)
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex gap-3 justify-end">
                    <button type="button"
                            @click="closeAreaForm()"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span x-text="areaForm.id ? 'Aktualisieren' : 'Erstellen'"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Bereiche Liste --}}
        @if($areas->isEmpty())
            <div class="alert-diagnostic alert-info">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Noch keine Diagnosebereiche</h3>
                        <p class="mt-1 text-sm text-blue-700">
                            Klicken Sie auf "Neuer Bereich" um zu beginnen.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="space-y-4">
                @foreach($areas as $area)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" data-area-id="{{ $area->id }}">
                        {{-- Area Header --}}
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 px-6 py-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <button type="button"
                                        class="flex items-center gap-3 text-left flex-grow group"
                                        @click="toggleArea({{ $area->id }})">
                                    <svg class="w-5 h-5 text-blue-600 transform transition-transform group-hover:scale-110"
                                         :class="isAreaExpanded({{ $area->id }}) ? 'rotate-90' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                            {{ $area->name }}
                                            @if(!$area->active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700">
                                                    Inaktiv
                                                </span>
                                            @endif
                                        </h3>
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $area->stages->count() }} {{ $area->stages->count() == 1 ? 'Stufe' : 'Stufen' }}</p>
                                    </div>
                                </button>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('diagnostic.export-blank-form-pdf', $area->id) }}"
                                       class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 transition-colors"
                                       target="_blank"
                                       title="Leerformular als PDF exportieren">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                        PDF
                                    </a>
                                    <button type="button"
                                            class="inline-flex items-center px-3 py-1.5 border border-green-300 text-sm font-medium rounded-md text-green-700 bg-white hover:bg-green-50 transition-colors"
                                            @click="openStageForm({{ $area->id }})">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Stufe
                                    </button>
                                    <button type="button"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                            @click="editArea({{ $area->id }}, '{{ addslashes($area->name) }}', '{{ addslashes($area->description) }}', {{ $area->active ? 'true' : 'false' }})">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Bearbeiten
                                    </button>
                                    <button type="button"
                                            class="inline-flex items-center px-3 py-1.5 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 transition-colors"
                                            @click="deleteArea({{ $area->id }}, '{{ addslashes($area->name) }}')">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Löschen
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Area Content --}}
                        <div x-show="isAreaExpanded({{ $area->id }})"
                             x-collapse
                             style="display: none;">
                            <div class="px-6 py-4">
                                {{-- Bereichsziel --}}
                                @if($area->description)
                                    <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-blue-800">Bereichsziel</p>
                                                <p class="mt-1 text-sm text-blue-700">{{ $area->description }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Inline Stage Form --}}
                                <div x-show="showStageForm[{{ $area->id }}]"
                                     x-collapse
                                     id="stage-form-{{ $area->id }}"
                                     class="ml-4 mt-3 bg-white rounded-lg shadow-sm border border-green-300 overflow-hidden">
                                    <div class="bg-green-50 border-b border-green-200 px-4 py-2">
                                        <h4 class="text-base font-semibold text-gray-900" x-text="stageForm.id ? 'Stufe bearbeiten' : 'Neue Stufe'"></h4>
                                    </div>
                                    <form @submit.prevent="saveStage()" class="p-4">
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                                <input type="text"
                                                       x-model="stageForm.name"
                                                       placeholder="z.B. Stufe I"
                                                       required
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Code *</label>
                                                <input type="text"
                                                       x-model="stageForm.code"
                                                       placeholder="z.B. I, II, III..."
                                                       required
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Stufenziel (Beschreibung)</label>
                                                <textarea x-model="stageForm.goal_description"
                                                          rows="2"
                                                          placeholder="Optionale Beschreibung des Stufenziels"
                                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 text-sm"></textarea>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex gap-2 justify-end">
                                            <button type="button"
                                                    @click="closeStageForm({{ $area->id }})"
                                                    class="px-3 py-1.5 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                Abbrechen
                                            </button>
                                            <button type="submit"
                                                    class="px-3 py-1.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                <span x-text="stageForm.id ? 'Aktualisieren' : 'Erstellen'"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                {{-- Stufen --}}
                                @if($area->stages->isEmpty())
                                    <div class="alert-diagnostic alert-warning">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-6 w-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-yellow-800">Keine Stufen vorhanden</h3>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="space-y-3">
                                        @foreach($area->stages as $stage)
                                            <div class="ml-4 bg-gray-50 rounded-lg border border-gray-200 overflow-hidden" data-stage-id="{{ $stage->id }}">
                                                {{-- Stage Header --}}
                                                <div class="bg-white border-b border-gray-200 px-4 py-3">
                                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                                        <button type="button"
                                                                class="flex items-center gap-2 text-left flex-grow group"
                                                                @click="toggleStage({{ $stage->id }})">
                                                            <svg class="w-4 h-4 text-gray-600 transform transition-transform"
                                                                 :class="isStageExpanded({{ $stage->id }}) ? 'rotate-90' : ''"
                                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                            </svg>
                                                            <div>
                                                                <h4 class="text-base font-semibold text-gray-900">
                                                                    {{ $stage->name }} <span class="text-gray-500">({{ $stage->code }})</span>
                                                                </h4>
                                                                <p class="text-xs text-gray-500">{{ $stage->goals->count() }} {{ $stage->goals->count() == 1 ? 'Ziel' : 'Ziele' }}</p>
                                                            </div>
                                                        </button>
                                                        <div class="flex flex-wrap gap-2">
                                                            <button type="button"
                                                                    class="inline-flex items-center px-2.5 py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-white hover:bg-green-50 transition-colors"
                                                                    @click="openGoalForm({{ $stage->id }})">
                                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                                </svg>
                                                                Ziel
                                                            </button>
                                                            <button type="button"
                                                                    class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                                                    @click="editStage({{ $stage->id }}, {{ $area->id }}, '{{ addslashes($stage->name) }}', '{{ addslashes($stage->code) }}', '{{ addslashes($stage->goal_description) }}')">
                                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                </svg>
                                                                Bearbeiten
                                                            </button>
                                                            <button type="button"
                                                                    class="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 transition-colors"
                                                                    @click="deleteStage({{ $stage->id }}, '{{ addslashes($stage->name) }}')">
                                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                </svg>
                                                                Löschen
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Stage Content --}}
                                                <div x-show="isStageExpanded({{ $stage->id }})"
                                                     x-collapse
                                                     style="display: none;">
                                                    <div class="px-4 py-3">
                                                        {{-- Stufenziel --}}
                                                        @if($stage->goal_description)
                                                            <div class="mb-3 bg-gray-100 border-l-4 border-gray-400 p-3 rounded">
                                                                <p class="text-xs font-medium text-gray-800">Stufenziel</p>
                                                                <p class="mt-1 text-xs text-gray-700">{{ $stage->goal_description }}</p>
                                                            </div>
                                                        @endif

                                                        {{-- Ziele --}}
                                                        @if($stage->goals->isEmpty())
                                                            <div class="text-sm text-yellow-600 bg-yellow-50 border border-yellow-200 rounded p-3">
                                                                Noch keine Ziele vorhanden.
                                                            </div>
                                                        @else
                                                            <div class="overflow-hidden rounded-lg border border-gray-200">
                                                                <table class="min-w-full divide-y divide-gray-200">
                                                                    <thead class="bg-gray-50">
                                                                        <tr>
                                                                            <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider" style="width: 100px;">
                                                                                Code
                                                                            </th>
                                                                            <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                                                                Beschreibung
                                                                            </th>
                                                                            <th scope="col" class="px-4 py-2 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider" style="width: 150px;">
                                                                                Aktionen
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                                        @foreach($stage->goals as $goal)
                                                                            <tr data-goal-id="{{ $goal->id }}" class="hover:bg-gray-50 transition-colors">
                                                                                <td class="px-4 py-3 whitespace-nowrap">
                                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                                        {{ $goal->code }}
                                                                                    </span>
                                                                                </td>
                                                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                                                    {{ $goal->description }}
                                                                                </td>
                                                                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                                                                    <div class="inline-flex gap-1">
                                                                                        <button type="button"
                                                                                                class="inline-flex items-center px-2 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                                                                                @click="editGoal({{ $goal->id }}, {{ $stage->id }}, '{{ addslashes($goal->code) }}', '{{ addslashes($goal->description) }}')">
                                                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                                            </svg>
                                                                                        </button>
                                                                                        <button type="button"
                                                                                                class="inline-flex items-center px-2 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 transition-colors"
                                                                                                @click="deleteGoal({{ $goal->id }}, '{{ addslashes($goal->code) }}')">
                                                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                                            </svg>
                                                                                        </button>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        @endif

                                                        {{-- Inline Goal Form --}}
                                                        <div x-show="showGoalForm[{{ $stage->id }}]"
                                                             x-collapse
                                                             id="goal-form-{{ $stage->id }}"
                                                             class="mt-3 bg-white rounded-lg shadow-sm border border-blue-300 overflow-hidden">
                                                            <div class="bg-blue-50 border-b border-blue-200 px-4 py-2">
                                                                <h5 class="text-sm font-semibold text-gray-900" x-text="goalForm.id ? 'Ziel bearbeiten' : 'Neues Ziel'"></h5>
                                                            </div>
                                                            <form @submit.prevent="saveGoal()" class="p-4">
                                                                <div class="space-y-3">
                                                                    <div>
                                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Code *</label>
                                                                        <input type="text"
                                                                               x-model="goalForm.code"
                                                                               placeholder="z.B. V-1"
                                                                               required
                                                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm">
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung *</label>
                                                                        <textarea x-model="goalForm.description"
                                                                                  rows="3"
                                                                                  placeholder="Inkl. Modalitäten und Querverweise zu anderen Bereichen"
                                                                                  required
                                                                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-4 flex gap-2 justify-end">
                                                                    <button type="button"
                                                                            @click="closeGoalForm({{ $stage->id }})"
                                                                            class="px-3 py-1.5 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                        Abbrechen
                                                                    </button>
                                                                    <button type="submit"
                                                                            class="px-3 py-1.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                        <span x-text="goalForm.id ? 'Aktualisieren' : 'Erstellen'"></span>
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
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

