@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap">
            <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
            </a>
            <h3 class="mb-0">Aufgaben &amp; Rollen – {{ $meeting->title }}</h3>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                {{ $meeting->date->format('d.m.Y') }} &middot; {{ $meeting->start_time }} - {{ $meeting->end_time }}
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Mitarbeiter</th>
                                <th>Rolle</th>
                                <th>Notizen</th>
                                <th class="text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tasks as $task)
                                <tr>
                                    <form action="{{ route('meetings.tasks.update', ['group' => $group->name, 'meeting' => $meeting->id, 'task' => $task->id]) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <td>
                                            <select name="user_id" class="form-control form-control-sm">
                                                @foreach($users as $user)
                                                    <option value="{{ $user->id }}" @if($task->user_id == $user->id) selected @endif>{{ $user->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="role" value="{{ $task->role }}" class="form-control form-control-sm">
                                        </td>
                                        <td>
                                            <input type="text" name="notes" value="{{ $task->notes }}" class="form-control form-control-sm">
                                        </td>
                                        <td class="text-right text-nowrap">
                                            <button type="submit" class="btn btn-success btn-sm">Speichern</button>
                                    </form>
                                            <form action="{{ route('meetings.tasks.delete', ['group' => $group->name, 'meeting' => $meeting->id, 'task' => $task->id]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Wirklich löschen?')">Löschen</button>
                                            </form>
                                        </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted">Noch keine Aufgaben/Rollen vergeben.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <hr>

                <h5>Neue Aufgabe / Rolle</h5>
                <form action="{{ route('meetings.tasks.add', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST">
                    @csrf
                    <div class="form-row">
                        <div class="col-12 col-md-4 mb-2">
                            <label for="user_id">Mitarbeiter</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Bitte wählen</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3 mb-2">
                            <label for="role">Rolle</label>
                            <input type="text" name="role" id="role" class="form-control" placeholder="z. B. Protokollant" required>
                        </div>
                        <div class="col-12 col-md-4 mb-2">
                            <label for="notes">Notizen</label>
                            <input type="text" name="notes" id="notes" class="form-control" placeholder="optional">
                        </div>
                        <div class="col-12 col-md-1 mb-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block">+</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

