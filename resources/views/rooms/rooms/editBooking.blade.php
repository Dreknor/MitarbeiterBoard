@extends('layouts.app')

@push('css')
    @vite(['resources/css/rooms.css'])
@endpush

@section('content')
<div class="rooms-wrapper">
<div class="max-w-2xl mx-auto px-3 sm:px-4 md:px-6 py-4 md:py-6">

    {{-- Zurück --}}
    <a href="{{ url('rooms/rooms/'.$room->id) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 shadow-sm mb-5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        {{ $room->name }}
    </a>

    {{-- Buchung bearbeiten --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-5">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            @if($booking->is_recurring)
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <div>
                    <h1 class="font-semibold text-gray-800">Wiederkehrende Buchung bearbeiten</h1>
                    <p class="text-xs text-gray-400">{{ $room->name }} ({{ $room->room_number }})</p>
                </div>
            @else
                <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div>
                    <h1 class="font-semibold text-gray-800">Einzeltermin bearbeiten</h1>
                    <p class="text-xs text-gray-400">{{ $room->name }} ({{ $room->room_number }})</p>
                </div>
            @endif
        </div>

        <div class="px-5 py-5">
            @if(!$booking->is_recurring)
                <div class="mb-4 flex items-start gap-2 rounded-xl bg-teal-50 border border-teal-200 px-4 py-3 text-teal-700 text-sm">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Dies ist ein Einzeltermin – die Änderung gilt nur für diesen Tag.
                </div>
            @endif

            <form method="post" action="{{ url('rooms/bookings/'.$booking->id) }}">
                @csrf
                @method('put')
                <input type="hidden" name="room_id" value="{{ $room->id }}">
                <input type="hidden" name="is_recurring" value="{{ $booking->is_recurring ? '1' : '0' }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    @if($booking->is_recurring)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Wochentag <span class="text-red-500">*</span></label>
                            <select name="weekday" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option disabled selected></option>
                                @foreach(config('config.days') as $key => $day)
                                    <option value="{{ $key }}" @if(old('weekday', $booking->weekday) == $key) selected @endif>{{ $day }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Woche</label>
                            <select name="week" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="" @if(old('week', $booking->week) === null) selected @endif>Jede</option>
                                <option value="A" @if(old('week', $booking->week) == 'A') selected @endif>A-Woche</option>
                                <option value="B" @if(old('week', $booking->week) == 'B') selected @endif>B-Woche</option>
                            </select>
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Datum <span class="text-red-500">*</span></label>
                            <input type="date" name="booking_date"
                                   class="w-full sm:w-auto rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                                   value="{{ old('booking_date', $booking->booking_date ? $booking->booking_date->format('Y-m-d') : '') }}">
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start <span class="text-red-500">*</span></label>
                        <input type="time" name="start"
                               class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                               value="{{ old('start', $booking->start) }}"
                               min="{{ \Carbon\Carbon::createFromTimeString(config('rooms.start_booking'))->format('H:i') }}"
                               max="{{ \Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))->subMinutes(15)->format('H:i') }}"
                               step="300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ende <span class="text-red-500">*</span></label>
                        <input type="time" name="end"
                               class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                               value="{{ old('end', $booking->end) }}"
                               min="{{ \Carbon\Carbon::createFromTimeString(config('rooms.start_booking'))->addMinutes(15)->format('H:i') }}"
                               max="{{ \Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))->format('H:i') }}"
                               step="300">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bezeichnung <span class="text-red-500">*</span></label>
                        <input type="text" maxlength="60" name="name"
                               class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                               value="{{ old('name', $booking->name) }}" placeholder="z.B. Mathe AG">
                    </div>
                </div>

                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Speichern
                </button>
            </form>
        </div>
    </div>

    {{-- Buchung löschen --}}
    <div class="bg-white rounded-2xl border border-red-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-red-100 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            <h2 class="font-semibold text-red-700">Buchung löschen</h2>
        </div>
        <div class="px-5 py-4">
            <p class="text-sm text-gray-600 mb-4">
                @if($booking->is_recurring)
                    Diese wiederkehrende Buchung wird dauerhaft und vollständig gelöscht.
                @else
                    Dieser Einzeltermin wird dauerhaft gelöscht.
                @endif
            </p>
            <form method="post" action="{{ url('rooms/booking/'.$booking->id) }}">
                @csrf
                @method('delete')
                <button type="submit"
                        onclick="return confirm('Buchung wirklich löschen?')"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Buchung löschen
                </button>
            </form>
        </div>
    </div>

</div>
</div>
@endsection
