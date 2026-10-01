@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@section('content')
<div class="meeting-wrapper" x-data="{ showInfo: false }" x-cloak>

    <nav class="mtg-breadcrumb" aria-label="Brotkrumen">
        <a href="{{ route('meetings.overview') }}"><i class="fas fa-users"></i> Meetings</a>
        <span class="sep">/</span>
        <a href="{{ route('meetings.show', $meeting) }}" class="truncate">{{ $meeting->title }}</a>
        <span class="sep">/</span>
        <span>Protokoll</span>
    </nav>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="mtg-page-title text-2xl font-bold text-gray-900">Protokoll</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $meeting->title }} · {{ $meeting->date->format('d.m.Y') }} · {{ $meeting->start_time }} – {{ $meeting->end_time }} Uhr
            </p>
        </div>
        <form action="{{ route('meetings.protocol.pdf', $meeting) }}" method="POST" class="flex flex-wrap items-center gap-4">
            @csrf
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="info" value="1" class="accent-blue-600" x-model="showInfo"> Themen-Informationen aufnehmen
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="closed" value="1" class="accent-blue-600"> „Thema geschlossen“ aufnehmen
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="changed" value="1" class="accent-blue-600"> Verschiebungen aufnehmen
            </label>
            <button type="submit" class="mtg-btn mtg-btn-primary"><i class="fas fa-file-pdf"></i> Als PDF herunterladen</button>
        </form>
    </div>

    <section class="mtg-card p-5 mb-6 text-sm">
        <dl class="space-y-2">
            <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Meeting</dt><dd class="text-gray-900">{{ $meeting->contextLabel() }}</dd></div>
            <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Datum</dt><dd class="text-gray-900">{{ $meeting->date->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</dd></div>
            <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Anwesend</dt><dd class="text-gray-900">{{ $attendance['present']->implode(', ') ?: '–' }}</dd></div>
            @if($attendance['online']->isNotEmpty())
                <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Online</dt><dd class="text-gray-900">{{ $attendance['online']->implode(', ') }}</dd></div>
            @endif
            <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Entschuldigt</dt><dd class="text-gray-900">{{ $attendance['excused']->implode(', ') ?: '–' }}</dd></div>
            @if($attendance['guests']->isNotEmpty())
                <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Gäste</dt><dd class="text-gray-900">{{ $attendance['guests']->implode(', ') }}</dd></div>
            @endif
            @if($attendance['recorded'] === false)
                <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0"></dt><dd class="text-amber-700 text-xs">Anwesenheit wurde noch nicht erfasst – <a class="mtg-link" href="{{ route('meetings.show', $meeting) }}">jetzt erfassen</a>.</dd></div>
            @endif
            @if($meeting->meetingTasks->isNotEmpty())
                <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Rollen</dt>
                    <dd class="text-gray-900">
                        @foreach($meeting->meetingTasks as $task){{ $task->role }}: {{ $task->user?->name }}@if(! $loop->last), @endif @endforeach
                    </dd>
                </div>
            @endif
            @if($authors->isNotEmpty())
                <div class="flex gap-3"><dt class="w-36 text-gray-500 shrink-0">Protokoll von</dt><dd class="text-gray-900">{{ $authors->implode(', ') }}</dd></div>
            @endif
        </dl>
    </section>

    @forelse($themes as $theme)
        <section class="mtg-card mb-4">
            <div class="mtg-card-head">
                <h2 class="mtg-card-title">{{ $loop->iteration }}. {{ $theme->theme }}</h2>
                <a href="{{ route('meetings.themes.show', [$meeting, $theme]) }}" class="mtg-btn mtg-btn-secondary mtg-btn-sm">
                    <i class="fas fa-pen-nib"></i> Protokollieren
                </a>
            </div>
            <div class="p-5 space-y-3">
                @if(filled(strip_tags((string) $theme->information)))
                    <div x-show="showInfo" style="display:none;" class="rounded-xl bg-gray-50 border border-gray-100 p-3">
                        <div class="text-xs font-semibold text-gray-500 mb-1">Informationen zum Thema</div>
                        <div class="mtg-prose">{!! $theme->information !!}</div>
                    </div>
                @endif
                @foreach($theme->protocols as $protocol)
                    <div>
                        <div class="text-xs text-gray-500 mb-1">{{ $protocol->ersteller->name }} · {{ $protocol->created_at->format('H:i') }} Uhr</div>
                        <div class="mtg-prose">{!! $protocol->protocol !!}</div>
                    </div>
                @endforeach
                @if($theme->tasks->isNotEmpty())
                    <div class="pt-3 border-t border-gray-100">
                        <div class="text-xs font-semibold text-gray-500 mb-1">Aufgaben</div>
                        <ul class="list-disc pl-5 text-sm text-gray-800">
                            @foreach($theme->tasks as $task)
                                <li>{{ $task->taskable->name ?? '' }}{{ ($task->taskable->name ?? '') !== '' ? ' – ' : '' }}{{ $task->task }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @empty
        <div class="mtg-card p-8 text-center text-gray-500">
            <i class="far fa-clipboard text-3xl text-gray-300 mb-3 block"></i>
            Für dieses Meeting wurden noch keine Protokolleinträge erfasst.
            <div class="mt-4">
                <a href="{{ route('meetings.show', $meeting) }}" class="mtg-btn mtg-btn-primary"><i class="fas fa-list-ol"></i> Zur Agenda</a>
            </div>
        </div>
    @endforelse
</div>
@endsection
