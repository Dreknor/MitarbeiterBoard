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
    <div class="mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('diagnostic.areas', $session->schueler_id) }}"
                   class="btn-diagnostic-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Zurück
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        {{ $session->schueler->name }} - {{ $session->area->name }}
                    </h1>
                    <p class="text-sm text-gray-500 mt-1 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        {{ $session->session_date->format('d.m.Y') }}
                    </p>
                </div>
            </div>
            <div>
                @if($session->is_completed)
                    <span class="badge-diagnostic badge-success">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        Abgeschlossen
                    </span>
                @else
                    <span class="badge-diagnostic badge-warning">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        In Bearbeitung
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Debug Info (nur für Entwicklung) --}}
    @if(config('app.debug'))
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-xs">
            <strong>Debug Info:</strong><br>
            Session ID: {{ $session->id }}<br>
            Is Completed: {{ $session->is_completed ? 'Ja' : 'Nein' }}<br>
            Completed At: {{ $session->completed_at ?? 'NULL' }}<br>
            User ID: {{ auth()->id() }}<br>
            Can Complete: {{ auth()->user()->can('complete', $session) ? 'Ja' : 'Nein' }}<br>
            Has Permission 'view diagnostics': {{ auth()->user()->hasPermissionTo('view diagnostics') ? 'Ja' : 'Nein' }}<br>
            Schueler Klasse: {{ $session->schueler->klasse_id ?? 'NULL' }}<br>
            User has access to class: {{ auth()->user()->paed_klassen()->where('klassen.id', $session->schueler->klasse_id)->exists() ? 'Ja' : 'Nein' }}
        </div>
    @endif

<div x-data="diagnosticSession"
     data-session-id="{{ $session->id }}"
     data-schueler-id="{{ $session->schueler_id }}"
     x-init="
    ratings = @js($session->assessments->pluck('rating', 'diagnostic_goal_id')->toArray());
    currentGoals = @js($session->assessments->mapWithKeys(function($assessment) {
        return [$assessment->diagnostic_goal_id => (bool)$assessment->is_current_goal];
    })->toArray());
    stageNotes = @js($session->stageNotes->pluck('notes', 'diagnostic_stage_id')->toArray());
    assessmentIds = @js($session->assessments->pluck('id', 'diagnostic_goal_id')->toArray());
    showComments = {};
    newComment = {};
    @foreach($session->area->stages as $stage)
        @foreach($stage->goals as $goal)
            showComments[{{ $goal->id }}] = false;
            newComment[{{ $goal->id }}] = '';
        @endforeach
    @endforeach
">
    {{-- Area Goal Card --}}
    @if($session->area->description)
        <div class="area-goal-card bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-semibold text-purple-800 uppercase tracking-wide">Bereichsziel</h3>
                    <p class="mt-1 text-sm text-gray-700">{{ $session->area->description }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Auto-Save Indicator --}}
    <div class="fixed top-20 right-4 z-50 space-y-2">
        <div x-show="saving"
             x-cloak
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-x-full opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             class="save-indicator bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Speichert...
        </div>

        <div x-show="saved"
             x-cloak
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-x-full opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             class="save-indicator bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
            <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            Gespeichert
        </div>

        <div x-show="error"
             x-cloak
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-x-full opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             class="save-indicator bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
            <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            Fehler beim Speichern
        </div>
    </div>

    {{-- Tabs for Stages --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="{ activeTab: 'stage-{{ $session->area->stages->first()->id ?? 0 }}' }">
        <div class="border-b-2 border-gray-200 bg-gray-50">
            <nav class="flex space-x-1 px-4 overflow-x-auto">
                @foreach($session->area->stages as $index => $stage)
                    <button @click="activeTab = 'stage-{{ $stage->id }}'"
                            :class="activeTab === 'stage-{{ $stage->id }}' ? 'bg-blue-600 text-white font-bold shadow-md border-b-4 border-blue-800' : 'bg-gray-200 border-2 border-gray-300 text-gray-700 hover:text-gray-900 hover:bg-gray-300 hover:border-gray-400'"
                            class="whitespace-nowrap py-3 px-6 text-sm transition-all duration-200 rounded-t-lg -mb-0.5 relative min-w-[120px]">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" :class="activeTab === 'stage-{{ $stage->id }}' ? 'text-white' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>{{ $stage->name }}</span>
                        </span>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Tab Content --}}
        <div>
            @foreach($session->area->stages as $index => $stage)
                <div x-show="activeTab === 'stage-{{ $stage->id }}'"
                     x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="p-6 space-y-6">

                    {{-- Stage Goal --}}
                    @if($stage->goal_description)
                        <div class="stage-goal-card bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-xs font-semibold text-blue-800 uppercase tracking-wide">Stufenziel</h4>
                                    <p class="mt-1 text-sm text-gray-700">{{ $stage->goal_description }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Stage Notes --}}
                    <div class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-4 py-3">
                            <h3 class="text-sm font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Notizen zu {{ $stage->name }}
                            </h3>
                        </div>
                        <div class="p-4">
                            <textarea
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none transition-all"
                                rows="3"
                                placeholder="Notizen zu dieser Stufe..."
                                x-model="stageNotes[{{ $stage->id }}]"
                                @input.debounce.500ms="saveStageNote({{ $stage->id }})"
                                {{ $session->is_completed ? 'disabled' : '' }}
                            ></textarea>
                        </div>
                    </div>

                    {{-- Goals Table --}}
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                Ziele ({{ $stage->goals->count() }})
                            </h3>
                        </div>

                        <div class="overflow-x-auto diagnostic-table-wrapper">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                                            Code
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Beschreibung
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                                            Historie
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-80">
                                            Bewertung
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                            Aktuell
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                                            Kommentar
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($stage->goals as $goal)
                                        @php
                                            $assessment = $session->assessments->where('diagnostic_goal_id', $goal->id)->first();
                                            $currentRating = $assessment->rating ?? null;
                                            $isCurrentGoal = $assessment->is_current_goal ?? false;
                                            $history = $historicalData[$goal->id] ?? [];
                                            $goalComments = $comments[$goal->id] ?? collect();
                                        @endphp
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-semibold bg-gray-100 text-gray-800">
                                                    {{ $goal->code }}
                                                </span>
                                            </td>

                                            <td class="px-4 py-3 text-sm text-gray-900">
                                                {{ $goal->description }}
                                            </td>

                                            <td class="px-4 py-3 text-center">
                                                <div class="flex justify-center items-center space-x-1">
                                                    @forelse($history as $h)
                                                        <span class="history-circle cursor-help"
                                                              style="background-color: {{ $h['color'] }};"
                                                              title="{{ $h['date'] }}: {{ $h['rating_text'] }}">
                                                        </span>
                                                    @empty
                                                        <span class="text-gray-400 text-sm">-</span>
                                                    @endforelse
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-2">
                                                    <!-- Kann es Button -->
                                                    <button type="button"
                                                            class="rating-btn-modern rating-btn-white"
                                                            @click="setRating({{ $goal->id }}, {{ $assessment->id ?? 0 }}, 'white')"
                                                            {{ $session->is_completed ? 'disabled' : '' }}
                                                            :class="{
                                                                'active': ratings[{{ $goal->id }}] === 'white',
                                                                'opacity-50 cursor-not-allowed': {{ $session->is_completed ? 'true' : 'false' }}
                                                            }">
                                                        <span class="sr-only">Kann es</span>
                                                    </button>

                                                    <!-- Aktuelles Ziel Button -->
                                                    <button type="button"
                                                            class="rating-btn-modern rating-btn-gray"
                                                            @click="setRating({{ $goal->id }}, {{ $assessment->id ?? 0 }}, 'gray')"
                                                            {{ $session->is_completed ? 'disabled' : '' }}
                                                            :class="{
                                                                'active': ratings[{{ $goal->id }}] === 'gray',
                                                                'opacity-50 cursor-not-allowed': {{ $session->is_completed ? 'true' : 'false' }}
                                                            }">
                                                        <span class="sr-only">Aktuelles Ziel</span>
                                                    </button>

                                                    <!-- Kann es nicht Button -->
                                                    <button type="button"
                                                            class="rating-btn-modern rating-btn-dark"
                                                            @click="setRating({{ $goal->id }}, {{ $assessment->id ?? 0 }}, 'dark_gray')"
                                                            {{ $session->is_completed ? 'disabled' : '' }}
                                                            :class="{
                                                                'active': ratings[{{ $goal->id }}] === 'dark_gray',
                                                                'opacity-50 cursor-not-allowed': {{ $session->is_completed ? 'true' : 'false' }}
                                                            }">
                                                        <span class="sr-only">Kann es nicht</span>
                                                    </button>
                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-center">
                                                <input type="checkbox"
                                                       class="diagnostic-checkbox"
                                                       id="current-{{ $goal->id }}"
                                                       :checked="currentGoals[{{ $goal->id }}]"
                                                       @click="toggleCurrentGoal({{ $goal->id }}, {{ $assessment->id ?? 0 }}, $event)"
                                                       :disabled="!ratings[{{ $goal->id }}] || ratings[{{ $goal->id }}] !== 'gray' || {{ $session->is_completed ? 'true' : 'false' }}"
                                                       {{ (!$assessment || $currentRating !== 'gray' || $session->is_completed) ? 'disabled' : '' }}>
                                            </td>

                                            <td class="px-4 py-3 text-center">
                                                <button type="button"
                                                        @click="toggleComments({{ $goal->id }})"
                                                        class="relative inline-flex items-center px-2 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                                        title="Kommentar">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    @if($goalComments->count() > 0)
                                                        <span class="absolute -top-2 -right-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform bg-blue-600 rounded-full">
                                                            {{ $goalComments->count() }}
                                                        </span>
                                                    @endif
                                                </button>
                                            </td>
                                        </tr>

                                        {{-- Inline Comment Row --}}
                                        <tr x-show="showComments[{{ $goal->id }}]" x-collapse class="bg-blue-50">
                                            <td colspan="6" class="px-4 py-3">
                                                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                                                    @if($goalComments->count() > 0)
                                                        <div class="space-y-2 mb-3">
                                                            @foreach($goalComments as $comment)
                                                                <div class="bg-white rounded p-3 border border-gray-200">
                                                                    <div class="flex justify-between items-start mb-1">
                                                                        <span class="text-xs font-medium text-gray-600">{{ $comment->user->name }} • {{ $comment->created_at->format('d.m.Y H:i') }}</span>
                                                                    </div>
                                                                    <p class="text-sm text-gray-800">{{ $comment->comment }}</p>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    <div class="space-y-2">
                                                        <textarea x-model="newComment[{{ $goal->id }}]"
                                                                  rows="2"
                                                                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                                                  placeholder="Neuer Kommentar..."></textarea>
                                                        <div class="flex justify-end gap-2">
                                                            <button @click="showComments[{{ $goal->id }}] = false; newComment[{{ $goal->id }}] = ''"
                                                                    type="button"
                                                                    class="px-3 py-1 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">
                                                                Abbrechen
                                                            </button>
                                                            <button @click="saveInlineComment({{ $goal->id }}, newComment[{{ $goal->id }}]); newComment[{{ $goal->id }}] = ''"
                                                                    type="button"
                                                                    :disabled="!newComment[{{ $goal->id }}] || !newComment[{{ $goal->id }}].trim()"
                                                                    class="px-3 py-1 text-sm text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                                                Speichern
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
            <a href="{{ route('diagnostic.areas', $session->schueler_id) }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Zurück zur Übersicht
            </a>

            <div class="flex space-x-3">
                @if($session->is_completed)
                    @can('manage diagnostics')
                        <form action="{{ route('diagnostic.reopen', $session->id) }}" method="POST" class="inline-block">
                            @csrf
                            <button type="submit"
                                    onclick="return confirm('Session wirklich wieder öffnen?')"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                                </svg>
                                Session wieder öffnen
                            </button>
                        </form>
                    @endcan
                @else
                    <a href="{{ route('diagnostic.export-session-pdf', $session->id) }}"
                       target="_blank"
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 transition-colors shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        PDF Export
                    </a>

                    @can('complete', $session)
                        <form action="{{ route('diagnostic.complete', $session->id) }}" method="POST" class="inline-block" id="complete-session-form">
                            @csrf
                            <button type="submit"
                                    onclick="return confirm('Session wirklich abschließen? Sie können danach keine Änderungen mehr vornehmen.')"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Session abschließen
                            </button>
                        </form>
                    @else
                        <!-- Debug: Kein Zugriff auf complete -->
                        <div class="text-xs text-red-500">
                            Sie haben keine Berechtigung zum Abschließen dieser Session.
                        </div>
                    @endcan
                @endif
            </div>
        </div>
    </div>
</div>
    </div>
</div>
@endsection


@push('js')
    @vite(['resources/js/diagnostics.js'])
    <script>
        // Initialize goal comments data
        window.goalComments = @json($formattedComments);
    </script>
@endpush

