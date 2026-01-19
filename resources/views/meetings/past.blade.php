@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="btn btn-primary mt-3">Zurück zur Übersicht</a>
        <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h3 class="card-title">Vergangene Meetings der Gruppe: {{ $group->name }}</h3>
        </div>
        <div class="card-body">
            @if($pastMeetings->isEmpty())
                <div class="alert alert-info">Es sind keine vergangenen Meetings vorhanden.</div>
            @else
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Uhrzeit</th>
                        <th>Themen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($pastMeetings as $meeting)
                        <tr>
                            <td>{{ $meeting->date->format('d.m.Y') }}</td>
                            <td>{{ $meeting->start_time }} - {{ $meeting->end_time }}</td>
                            <td>
                                @if($meeting->themes->count() > 0)
                                    <ul>
                                        @foreach($meeting->themes as $theme)
                                            <li>
                                                <a href="{{ route('themes.show', ['groupname' => $group->name, 'theme' => $theme->id]) }}">
                                                    {{ $theme->theme }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <em>Keine Themen</em>
                                @endif
                            </td>

                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection

