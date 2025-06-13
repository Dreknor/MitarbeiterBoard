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

        <div class="card">
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
                                    <strong>URL:</strong> <a href="{{ $meeting->url ?? '#' }}" target="_blank">{{ $meeting->url ?? 'Keine URL' }}</a>
                                </p>
                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#themes-{{ $meeting->id }}" aria-expanded="false" aria-controls="themes-{{ $meeting->id }}">
                                    Themen anzeigen
                                </button>
                                <div class="collapse" id="themes-{{ $meeting->id }}">
                                    <strong>Themenübersicht:</strong>
                                    <ul>
                                        @foreach($meeting->themes as $theme)
                                            <li>{{ $theme->title }}</li>
                                        @endforeach
                                    </ul>
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
