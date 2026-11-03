@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@section('content')
<div class="meeting-wrapper">

    <nav class="mtg-breadcrumb" aria-label="Brotkrumen">
        <a href="{{ route('meetings.overview') }}"><i class="fas fa-users"></i> Meetings</a>
        <span class="sep">/</span>
        <span>Archiv</span>
    </nav>

    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="mtg-page-title text-2xl font-bold text-gray-900">Meeting-Archiv</h1>
            <p class="text-sm text-gray-500 mt-0.5">Vergangene und abgesagte Meetings mit ihren Themen und Protokollen.</p>
        </div>
        <form method="GET" action="{{ route('meetings.archive') }}" class="relative w-full sm:w-72">
            @if($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="search" name="q" value="{{ $search }}" class="mtg-input" style="padding-left: 2rem" placeholder="Titel durchsuchen …">
        </form>
    </div>

    @include('meetings.partials.filter_pills', ['route' => 'meetings.archive'])

    <div class="mtg-card">
        @if($meetings->isEmpty())
            <div class="p-10 text-center text-sm text-gray-500">
                <i class="fas fa-archive text-3xl text-gray-300 mb-3 block"></i>
                Keine archivierten Meetings gefunden.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="mtg-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Meeting</th>
                            <th>Kontext</th>
                            <th class="text-right">Themen</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($meetings as $meeting)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-gray-900">{{ $meeting->date->format('d.m.Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $meeting->start_time }} – {{ $meeting->end_time }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('meetings.show', $meeting) }}" class="mtg-title-link font-semibold text-gray-900">{{ $meeting->title }}</a>
                                    @if($meeting->cancelled)
                                        <span class="mtg-badge mtg-badge-red ml-1">Abgesagt</span>
                                    @endif
                                    @if($meeting->creator)
                                        <div class="text-xs text-gray-500">organisiert von {{ $meeting->creator->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($meeting->isFree())
                                        <span class="mtg-badge mtg-badge-free"><i class="fas fa-globe"></i> Frei</span>
                                    @else
                                        <span class="mtg-badge mtg-badge-group"><i class="fas fa-users"></i> {{ $meeting->group?->name }}</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $meeting->themes_count }}</td>
                                <td class="text-right">
                                    <a href="{{ route('meetings.show', $meeting) }}" class="mtg-btn mtg-btn-secondary mtg-btn-sm">
                                        <i class="far fa-eye"></i> Öffnen
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($meetings->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 text-sm">
                    {{ $meetings->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
