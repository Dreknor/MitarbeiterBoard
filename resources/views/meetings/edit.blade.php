@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@section('content')
<div class="meeting-wrapper" x-data="{ showTasks: false }" x-cloak>
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="mtg-btn mtg-btn-secondary">
            <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
        </a>
    </div>

    <div class="mtg-card">
        <div class="mtg-band mtg-band-upcoming">
            <h1 class="text-lg font-bold">Meeting bearbeiten</h1>
        </div>
        <div class="p-5 sm:p-6 max-w-2xl">
            <form method="POST" action="{{ route('meetings.update', ['group' => $group->name, 'meeting' => $meeting->id]) }}" class="space-y-4">
                @csrf
                @method('PUT')
                @if($errors->any())
                    <div class="rounded border border-amber-300 bg-amber-50 text-amber-800 px-3 py-2 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif
                <div>
                    <label for="title" class="mtg-label">Titel</label>
                    <input type="text" class="mtg-input" id="title" name="title" value="{{ old('title', $meeting->title) }}" required>
                </div>
                <div>
                    <label for="date" class="mtg-label">Datum</label>
                    <input type="date" class="mtg-input" id="date" name="date" value="{{ old('date', $meeting->date ? $meeting->date->format('Y-m-d') : '') }}" required>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="start_time" class="mtg-label">Beginn</label>
                        <input type="time" class="mtg-input" id="start_time" name="start_time" value="{{ old('start_time', $meeting->start_time) }}" required>
                    </div>
                    <div>
                        <label for="end_time" class="mtg-label">Ende</label>
                        <input type="time" class="mtg-input" id="end_time" name="end_time" value="{{ old('end_time', $meeting->end_time) }}" required>
                    </div>
                </div>

                @include('meetings.partials.room_booking_fields', [
                    'prefix' => 'edit_meeting',
                    'bookRoomEnabled' => old('book_room', $meeting->roomBooking ? 1 : 0),
                    'selectedRoomId' => old('room_id', optional($meeting->roomBooking)->room_id),
                    'bookableRooms' => $bookableRooms,
                    'canBookRooms' => $canBookRooms,
                ])

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="mtg-btn mtg-btn-primary">Speichern</button>
                    <a href="{{ route('meetings.index', ['group' => $group->name]) }}" class="mtg-btn mtg-btn-secondary">Abbrechen</a>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-100">
                <button type="button" class="mtg-btn mtg-btn-secondary" @click="showTasks = true">
                    <i class="fas fa-user-tag"></i> Aufgaben &amp; Rollen verwalten
                </button>
            </div>
        </div>
    </div>

    @include('meetings.partials.tasks_modal', ['meeting' => $meeting, 'group' => $group])
</div>
@endsection
