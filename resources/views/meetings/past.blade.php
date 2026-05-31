@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="btn btn-primary mt-3">
            <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
        </a>
        <div class="card mt-2">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-0">
                    <i class="fas fa-archive"></i> Meetingsarchiv – {{ $group->name }}
                </h3>
                <small class="text-muted">{{ $pastMeetings->count() }} Meeting(s)</small>
            </div>
            <div class="card-body">
                @if($pastMeetings->isEmpty())
                    <div class="alert alert-info mb-0">Es sind keine vergangenen oder abgesagten Meetings vorhanden.</div>
                @else
                    @foreach($pastMeetings->groupBy(fn($m) => $m->date->format('Y')) as $jahr => $meetings)
                        <h5 class="mt-3 border-bottom pb-1">{{ $jahr }}</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Titel</th>
                                        <th>Uhrzeit</th>
                                        <th>Status</th>
                                        <th>Themen</th>
                                        <th>Aufgaben &amp; Rollen</th>
                                        <th class="text-right">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($meetings as $meeting)
                                        <tr>
                                            <td class="text-nowrap">{{ $meeting->date->format('d.m.Y') }}</td>
                                            <td>{{ $meeting->title }}</td>
                                            <td class="text-nowrap">{{ $meeting->start_time }} - {{ $meeting->end_time }}</td>
                                            <td>
                                                @if($meeting->cancelled)
                                                    <span class="badge badge-danger">Abgesagt</span>
                                                @else
                                                    <span class="badge badge-secondary">Durchgeführt</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($meeting->themes->count() > 0)
                                                    <ul class="mb-0 pl-3">
                                                        @foreach($meeting->themes as $theme)
                                                            <li>
                                                                <a href="{{ route('themes.show', ['groupname' => $group->name, 'theme' => $theme->id]) }}">
                                                                    {{ $theme->theme }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <em class="text-muted">Keine Themen</em>
                                                @endif
                                            </td>
                                            <td>
                                                @forelse($meeting->meetingTasks as $task)
                                                    <div><strong>{{ $task->role }}:</strong> {{ optional($task->user)->name }}</div>
                                                @empty
                                                    <em class="text-muted">–</em>
                                                @endforelse
                                            </td>
                                            <td class="text-right text-nowrap">
                                                <a href="{{ url($group->name.'/export/'.$meeting->date->format('Y-m-d')) }}"
                                                   class="btn btn-sm btn-outline-primary" title="Tagesprotokoll anzeigen">
                                                    <i class="fas fa-file-alt"></i>
                                                    <span class="d-none d-md-inline">Protokoll</span>
                                                </a>
                                                @if($meeting->cancelled)
                                                    <form action="{{ route('meetings.reactivate', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('Absage aufheben und Meeting wieder aktivieren?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Wieder aktivieren">
                                                            <i class="fas fa-undo"></i>
                                                            <span class="d-none d-md-inline">Aktivieren</span>
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
        </div>
    </div>
@endsection

