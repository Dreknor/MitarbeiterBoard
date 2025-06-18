@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="btn btn-primary">Zurück zur Übersicht</a>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>Meeting bearbeiten</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('meetings.update', ['group' => $group->name, 'meeting' => $meeting->id]) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="title" class="form-label">Titel</label>
                    <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $meeting->title) }}" required>
                </div>
                <div class="mb-3">
                    <label for="date" class="form-label">Datum</label>
                    <input type="date" class="form-control" id="date" name="date" value="{{ old('date', $meeting->date ? $meeting->date->format('Y-m-d') : '' ) }}" required>
                </div>
                <div class="mb-3">
                    <label for="start_time" class="form-label">Beginn</label>
                    <input type="time" class="form-control" id="start_time" name="start_time" value="{{ old('start_time', $meeting->start_time) }}" required>
                </div>
                <div class="mb-3">
                    <label for="end_time" class="form-label">Ende</label>
                    <input type="time" class="form-control" id="end_time" name="end_time" value="{{ old('end_time', $meeting->end_time) }}" required>
                </div>
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="btn btn-secondary">Abbrechen</a>
            </form>

        </div>
    </div>
</div>
@endsection

