@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@section('content')
<div class="meeting-wrapper" x-data="{ q: '' }" x-cloak>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="mtg-btn mtg-btn-secondary">
                <i class="fas fa-arrow-left"></i> <span class="hidden sm:inline">Übersicht</span>
            </a>
            <div>
                <h1 class="mtg-page-title text-2xl font-bold text-gray-900">
                    <i class="fas fa-archive text-gray-400 mr-1"></i> Meetingsarchiv
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">Gruppe: {{ $group->name }} · {{ $pastMeetings->count() }} Meeting(s)</p>
            </div>
        </div>
        <input x-model="q" type="search" placeholder="Suche nach Titel/Datum…" class="mtg-input w-full sm:w-64">
    </div>

    @if($pastMeetings->isEmpty())
        <div class="mtg-card p-8 text-center text-gray-500">
            <i class="fas fa-box-open text-3xl text-gray-300 mb-3 block"></i>
            Es sind keine vergangenen oder abgesagten Meetings vorhanden.
        </div>
    @else
        @foreach($pastMeetings->groupBy(fn($m) => $m->date->format('Y')) as $jahr => $meetings)
            <h2 class="mtg-section-title mt-6 mb-3">{{ $jahr }}</h2>
            <div class="mtg-card overflow-x-auto">
                <table class="mtg-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Titel</th>
                            <th class="whitespace-nowrap">Uhrzeit</th>
                            <th>Status</th>
                            <th>Themen</th>
                            <th>Aufgaben &amp; Rollen</th>
                            <th class="text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($meetings as $meeting)
                            <tr data-search="{{ \Illuminate\Support\Str::lower($meeting->title.' '.$meeting->date->format('d.m.Y').' '.$meeting->themes->pluck('theme')->implode(' ')) }}"
                                x-show="q === '' || $el.dataset.search.includes(q.toLowerCase())">
                                <td class="whitespace-nowrap font-medium text-gray-900">{{ $meeting->date->format('d.m.Y') }}</td>
                                <td>{{ $meeting->title }}</td>
                                <td class="whitespace-nowrap text-gray-600">{{ $meeting->start_time }} – {{ $meeting->end_time }}</td>
                                <td>
                                    @if($meeting->cancelled)
                                        <span class="mtg-badge mtg-badge-red">Abgesagt</span>
                                    @else
                                        <span class="mtg-badge mtg-badge-green">Durchgeführt</span>
                                    @endif
                                </td>
                                <td>
                                    @if($meeting->themes->count() > 0)
                                        <ul class="space-y-0.5">
                                            @foreach($meeting->themes as $theme)
                                                <li>
                                                    <a href="{{ route('themes.show', ['groupname' => $group->name, 'theme' => $theme->id]) }}"
                                                       class="text-blue-600 hover:underline">{{ $theme->theme }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-gray-400 italic">Keine Themen</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($meeting->meetingTasks as $task)
                                        <div class="text-gray-700"><strong>{{ $task->role }}:</strong> {{ optional($task->user)->name }}</div>
                                    @empty
                                        <span class="text-gray-400">–</span>
                                    @endforelse
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ url($group->name.'/export/'.$meeting->date->format('Y-m-d')) }}"
                                       class="mtg-btn mtg-btn-secondary mtg-btn-sm" title="Tagesprotokoll anzeigen">
                                        <i class="fas fa-file-alt"></i> <span class="hidden md:inline">Protokoll</span>
                                    </a>
                                    @if($meeting->cancelled)
                                        <form action="{{ route('meetings.reactivate', ['group' => $group->name, 'meeting' => $meeting->id]) }}"
                                              method="POST" class="inline"
                                              onsubmit="return confirm('Absage aufheben und Meeting wieder aktivieren?');">
                                            @csrf
                                            <button type="submit" class="mtg-btn mtg-btn-success mtg-btn-sm" title="Wieder aktivieren">
                                                <i class="fas fa-undo"></i> <span class="hidden md:inline">Aktivieren</span>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
</div>
@endsection

