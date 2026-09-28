@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@section('title', 'Meetings')

@section('content')
<div class="meeting-wrapper" x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }} }" x-cloak>

    {{-- Kopfbereich --}}
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="mtg-page-title text-2xl font-bold text-gray-900">Meetings</h1>
            <p class="text-sm text-gray-500 mt-0.5">Alle Besprechungen, an denen du teilnimmst – aus deinen Gruppen und freie Runden.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('meetings.archive') }}" class="mtg-btn mtg-btn-secondary">
                <i class="fas fa-archive"></i><span class="hidden sm:inline">Archiv</span>
            </a>
            @if($canCreate)
                <button type="button" class="mtg-btn mtg-btn-primary" @click="showCreate = true">
                    <i class="fas fa-plus"></i> Meeting planen
                </button>
            @endif
        </div>
    </div>

    {{-- Kennzahlen --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="mtg-stat">
            <span class="mtg-stat-icon bg-blue-50 text-blue-600"><i class="far fa-calendar-check"></i></span>
            <div><div class="mtg-stat-value">{{ $stats['heute'] }}</div><div class="mtg-stat-label">heute</div></div>
        </div>
        <div class="mtg-stat">
            <span class="mtg-stat-icon bg-indigo-50 text-indigo-600"><i class="far fa-calendar-alt"></i></span>
            <div><div class="mtg-stat-value">{{ $stats['woche'] }}</div><div class="mtg-stat-label">bis Wochenende</div></div>
        </div>
        <div class="mtg-stat">
            <span class="mtg-stat-icon bg-violet-50 text-violet-600"><i class="fas fa-globe"></i></span>
            <div><div class="mtg-stat-value">{{ $stats['frei'] }}</div><div class="mtg-stat-label">freie Meetings</div></div>
        </div>
        <div class="mtg-stat">
            <span class="mtg-stat-icon bg-amber-50 text-amber-600"><i class="fas fa-crown"></i></span>
            <div><div class="mtg-stat-value">{{ $stats['organisiert'] }}</div><div class="mtg-stat-label">von mir organisiert</div></div>
        </div>
    </div>

    {{-- Filter --}}
    @include('meetings.partials.filter_pills', ['route' => 'meetings.overview'])

    {{-- Liste --}}
    @forelse($sections as $label => $items)
        <section class="mb-6">
            <h2 class="mtg-section-title mb-3 flex items-center gap-2">
                @if($label === 'Heute')<span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>@endif
                {{ $label }}
                <span class="text-gray-400 font-normal normal-case">({{ $items->count() }})</span>
            </h2>
            <div class="space-y-3">
                @foreach($items as $meeting)
                    @include('meetings.partials.overview_row', ['meeting' => $meeting])
                @endforeach
            </div>
        </section>
    @empty
        <div class="mtg-card p-10 text-center">
            <div class="mx-auto w-14 h-14 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center text-2xl mb-4">
                <i class="far fa-calendar-plus"></i>
            </div>
            <h3 class="text-base font-semibold text-gray-900">Keine anstehenden Meetings</h3>
            <p class="text-sm text-gray-500 mt-1">
                @if($filter !== 'all')
                    Für diesen Filter gibt es keine Termine. <a href="{{ route('meetings.overview') }}" class="mtg-link">Alle anzeigen</a>
                @else
                    Sobald du zu einem Meeting eingeladen wirst, erscheint es hier.
                @endif
            </p>
            @if($canCreate)
                <button type="button" class="mtg-btn mtg-btn-primary mt-5" @click="showCreate = true">
                    <i class="fas fa-plus"></i> Meeting planen
                </button>
            @endif
        </div>
    @endforelse

    {{-- Modal: Meeting planen --}}
    @if($canCreate)
        <div class="mtg-modal-backdrop" x-show="showCreate" x-transition.opacity
             @keydown.escape.window="showCreate = false" style="display:none;">
            <div class="mtg-modal mtg-modal-lg" @click.outside="showCreate = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-3"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div class="mtg-modal-header">
                    <h3 class="mtg-modal-title">Meeting planen</h3>
                    <button type="button" class="mtg-modal-close" @click="showCreate = false" aria-label="Schließen">&times;</button>
                </div>
                <form action="{{ route('meetings.create') }}" method="POST" class="flex flex-col min-h-0">
                    @csrf
                    <div class="mtg-modal-body">
                        @include('meetings.partials.meeting_form', [
                            'meeting'   => null,
                            'selection' => ['users' => [], 'organizers' => [], 'groups' => [], 'roles' => []],
                        ])
                    </div>
                    <div class="mtg-modal-footer">
                        <button type="button" class="mtg-btn mtg-btn-secondary" @click="showCreate = false">Abbrechen</button>
                        <button type="submit" class="mtg-btn mtg-btn-primary"><i class="fas fa-check"></i> Meeting anlegen</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
