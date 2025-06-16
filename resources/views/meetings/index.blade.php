@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card ">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h3 class="card-title">Meetings</h3>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createMeetingModal">
                Meeting erstellen
            </button>
        </div>
        <div class="card-body">
            <!-- Modal -->
            <div class="modal fade" id="createMeetingModal" tabindex="-1" role="dialog" aria-labelledby="createMeetingModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createMeetingModalLabel">Neues Meeting erstellen</h5>

                        </div>
                        <div class="modal-body">
                            <form action="{{route('meetings.store',['group' => $group->name])}}" method="POST" class="form-horizontal">
                                @csrf
                                <div class="form-row">
                                    <div class="col-md-6">
                                        <label for="title">Titel des Meetings</label>
                                        <input type="text" class="form-control" name="title" id="title" required autofocus>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date">Datum</label>
                                        <input type="date" class="form-control" name="date" id="date" required min="{{Carbon\Carbon::now()->format('Y-m-d')}}">
                                    </div>
                                </div>
                                <div class="form-row mt-1">
                                    <div class="col-md-6">
                                        <label for="start_time">Startzeit</label>
                                        <input type="time" class="form-control" name="start_time" id="start_time" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_time">Endzeit</label>
                                        <input type="time" class="form-control" name="end_time" id="end_time" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">Meeting erstellen</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Ende Modal -->
        </div>
    </div>
        @if($meetingsToday->count())
            @foreach($meetingsToday as $meeting)
                <div class="card mb-3 border-primary ">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title">{{ $meeting->title }}</h5>
                    </div>
                    <div class="card-body">
                            <h6>Zeitraum:</h6>
                                <p>
                                    <i class="fa fa-calendar-alt text-info"></i> {{ $meeting->date->format('d.m.Y') }} <br>
                                    <i class="fa fa-clock text-info"></i> {{ $meeting->start_time }} - {{ $meeting->end_time }}<br>
                                </p>

                            @if($group->meeting_url)
                                <strong>URL:</strong> <a href="{{ $group->meeting_url}}" target="_blank">{{ $group->meeting_url}}</a>
                            @endif
                    </div>
                    <div class="card-footer">
                        <h6>Themenübersicht:</h6>
                        <ul>
                            @foreach($meeting->themes as $theme)
                                <li>{{ $theme->title }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        @endif

        <div class="card bg-light">
            <div class="card-header">
                <h5 class="card-title">
                    Nächste Meetings
                </h5>
            </div>
            <div class="card-body">
                @if($otherMeetings->count())
                    @foreach($otherMeetings as $meeting)
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">{{ $meeting->title }}</h5>
                                <p class="card-text">
                                    <strong>Zeitraum:</strong> {{ $meeting->date->format('d.m.Y') }} <br>
                                    <strong>Uhrzeit:</strong> {{ $meeting->start_time }} - {{ $meeting->end_time }}<br>
                                    @if($group->meeting_url)
                                        <strong>URL:</strong> <a href="{{ $group->meeting_url }}" target="_blank">{{ $group->meeting_url }}</a>
                                    @endif
                                </p>
                                @if($meeting->themes->count() > 0)
                                    <button class="btn btn-sm btn-info mt-2" type="button" data-toggle="collapse" data-target="#themes-{{ $meeting->id }}" aria-expanded="false" aria-controls="themes-{{ $meeting->id }}">
                                        {{$meeting->themes->count()}} Themen anzeigen
                                    </button>
                                    <div class="collapse" id="themes-{{ $meeting->id }}">
                                        <strong>Themenübersicht:</strong>
                                        <ul class="list-group">
                                            @foreach($meeting->themes as $theme)
                                                @include('meetings.elements.theme', ['theme' => $theme])
                                            @endforeach
                                        </ul>
                                    </div>
                                @else
                                    <p class="text-muted">Keine Themen für dieses Meeting festgelegt.</p>
                                @endif
                                <!-- Button zum Öffnen des Modals für Themen -->
                                <button class="btn btn-sm btn-success mt-2" data-toggle="modal" data-target="#addThemeModal-{{ $meeting->id }}">
                                    Thema hinzufügen/zuweisen
                                </button>
                                <!-- Modal für Themen anlegen/zuweisen -->
                                <div class="modal fade" id="addThemeModal-{{ $meeting->id }}" tabindex="-1" role="dialog" aria-labelledby="addThemeModalLabel-{{ $meeting->id }}" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="addThemeModalLabel-{{ $meeting->id }}">Thema anlegen oder zuweisen</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="{{ route('meetings.themes.store', [
                                                        'group' => $group->name,
                                                        'meeting' => $meeting->id,
                                                    ]) }}" method="POST">
                                                    @csrf
                                                    <h6>
                                                        Neues Thema anlegen
                                                    </h6>
                                                    <div class="form-group">
                                                        <div class="form-row">
                                                            <label for="theme_title_{{ $meeting->id }}">Neues Thema anlegen</label><div class="text-danger">*</div>
                                                            <input type="text" class="form-control" name="theme" id="theme_title_{{ $meeting->id }}" placeholder="Titel des Themas">
                                                        </div>
                                                        <div class="form-row">
                                                            <label for="theme_duration_{{ $meeting->id }}">Dauer</label><div class="text-danger">*</div>
                                                            <input type="number" class="form-control" name="duration" id="theme_duration_{{ $meeting->id }}" min="5" max="120" step="5" placeholder="Dauer in Minuten">
                                                        </div>
                                                        <div class="form-row">
                                                            <label for="type">Typ</label><div class="text-danger">*</div>
                                                            <select name="type" id="type" class="custom-select" required>
                                                                <option disabled></option>
                                                                @foreach($types as $type)
                                                                    <option value="{{$type->id}}" @if (old('type') == $type->id) selected @endif>{{$type->type}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="form-row">
                                                            <label for="theme_goal_{{ $meeting->id }}">Ziel</label><div class="text-danger">*</div>
                                                            <input type="text" class="form-control" name="goal" id="theme_goal_{{ $meeting->id }}" placeholder="Ziel">
                                                        </div>
                                                   </div>
                                                    <hr>
                                                    <h6>
                                                        Vorhandenes Thema zuweisen
                                                    </h6>
                                                    <div class="form-group">
                                                        <label for="existing_theme_{{ $meeting->id }}">Vorhandenes, offenes Thema zuweisen</label>
                                                        <select class="form-control" name="existing_theme_id" id="existing_theme_{{ $meeting->id }}">
                                                            <option value="">-- Bitte wählen --</option>
                                                            @foreach($openThemes as $openTheme)
                                                                <option value="{{ $openTheme->id }}">{{ $openTheme->theme }}
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">Speichern</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
            </div>
                    @endforeach
                @else
                    <p>Keine zukünftigen Meetings geplant.</p>
                @endif
        </div>
</div>
@endsection

@push('js')
    <script src="/js/priority-range.js"></script>
@endpush
