@extends('layouts.app')

@push('css')
    @vite(['resources/css/rooms.css'])
@endpush

@section('content')
<div class="rooms-wrapper">
<div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 py-4 md:py-6">

    {{-- Zurück-Button & Header --}}
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ url('rooms/rooms') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Alle Räume
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $room->name }}</h1>
                <p class="text-xs text-gray-400">
                    @if($room->room_number) Nr. {{ $room->room_number }} &bull; @endif
                    @if($room->bookable)
                        <span class="text-green-600 font-medium">Buchbar</span>
                    @else
                        <span class="text-red-500 font-medium">Nicht buchbar</span>
                    @endif
                </p>
            </div>
        </div>
        @if($room->bookable)
            <button id="toggleBookingBtn"
                    onclick="document.getElementById('bookingPanel').classList.toggle('hidden'); this.classList.toggle('bg-blue-700')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Neue Reservierung
            </button>
        @endif
    </div>

    {{-- Buchungsformular (ausklappbar) --}}
    @if($room->bookable)
    <div id="bookingPanel" class="hidden mb-5">
        <div class="bg-white rounded-2xl border border-blue-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-blue-50">
                <h2 class="font-semibold text-blue-800 text-sm">Neue Reservierung für {{ $room->name }}</h2>
            </div>
            <div class="px-5 py-4">
                {{-- Tabs --}}
                <div class="flex gap-1 mb-4 bg-gray-100 rounded-xl p-1 w-full sm:w-auto sm:inline-flex">
                    <button id="tab-recurring" onclick="switchTab('recurring')"
                            class="tab-btn flex-1 sm:flex-none text-center px-4 py-1.5 rounded-lg text-sm font-medium bg-white text-blue-700 shadow-sm">
                        Wiederkehrend
                    </button>
                    <button id="tab-single" onclick="switchTab('single')"
                            class="tab-btn flex-1 sm:flex-none text-center px-4 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-800">
                        Einzeltermin
                    </button>
                </div>

                {{-- Wiederkehrende Buchung --}}
                <div id="pane-recurring">
                    <form method="post" action="{{ url('rooms/bookings/') }}">
                        @csrf
                        <input type="hidden" name="room_id" value="{{ $room->id }}">
                        <input type="hidden" name="is_recurring" value="1">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                            <div class="col-span-2 sm:col-span-1 lg:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Wochentag(e) <span class="text-red-400">*</span></label>
                                <select name="weekdays[]" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required multiple size="5">
                                    @foreach(config('config.days') as $key => $day)
                                        <option value="{{ $key }}" @if(old('weekday') == $key) selected @endif>{{ $day }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Start <span class="text-red-400">*</span></label>
                                <input type="time" name="start" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                                       min="{{ config('rooms.start_booking') }}" max="{{ config('rooms.end_booking') }}" step="300" value="{{ old('start') }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ende <span class="text-red-400">*</span></label>
                                <input type="time" name="end" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                                       min="{{ config('rooms.start_booking') }}" max="{{ config('rooms.end_booking') }}" step="300" value="{{ old('end') }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Woche</label>
                                <select name="week" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option selected>Jede</option>
                                    <option value="A" @if(old('week') == 'A') selected @endif>A-Woche</option>
                                    <option value="B" @if(old('week') == 'B') selected @endif>B-Woche</option>
                                </select>
                            </div>
                            <div class="col-span-2 sm:col-span-2 lg:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bezeichnung <span class="text-red-400">*</span></label>
                                <input type="text" maxlength="60" name="name" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required value="{{ old('name') }}" placeholder="z.B. Mathe AG">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Speichern
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Einzeltermin --}}
                <div id="pane-single" class="hidden">
                    <form method="post" action="{{ url('rooms/bookings/') }}">
                        @csrf
                        <input type="hidden" name="room_id" value="{{ $room->id }}">
                        <input type="hidden" name="is_recurring" value="0">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Datum <span class="text-red-400">*</span></label>
                                <input type="date" name="booking_date" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required value="{{ old('booking_date', $date->format('Y-m-d')) }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Start <span class="text-red-400">*</span></label>
                                <input type="time" name="start" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                                       min="{{ config('rooms.start_booking') }}" max="{{ config('rooms.end_booking') }}" step="300" value="{{ old('start') }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ende <span class="text-red-400">*</span></label>
                                <input type="time" name="end" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required
                                       min="{{ config('rooms.start_booking') }}" max="{{ config('rooms.end_booking') }}" step="300" value="{{ old('end') }}">
                            </div>
                            <div class="col-span-2 sm:col-span-2 lg:col-span-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bezeichnung <span class="text-red-400">*</span></label>
                                <input type="text" maxlength="60" name="name" class="w-full rounded-xl border border-gray-300 px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required value="{{ old('name') }}" placeholder="z.B. Teammeeting">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Speichern
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Wochennavigation --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Navigation Header --}}
        <div class="px-4 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-2 flex-1">
                <a href="{{ url('rooms/rooms/'.$room->id.'/'.$week.'/'.$startOfWeek->copy()->subWeek()->format('Y-m-d')) }}"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div class="text-center flex-1">
                    <p class="font-semibold text-gray-800 text-sm">
                        KW {{ $startOfWeek->weekOfYear }}
                        &nbsp;({{ $startOfWeek->format('d.m.') }} – {{ $endOfWeek->format('d.m.Y') }})
                    </p>
                    @if($room->bookings()->where('week', '!=', null)->count() > 0)
                        <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 mt-0.5">
                            {{ $week }}-Woche
                        </span>
                    @endif
                </div>
                <a href="{{ url('rooms/rooms/'.$room->id.'/'.$week.'/'.$startOfWeek->copy()->addWeek()->format('Y-m-d')) }}"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            @if($room->bookings()->where('week', '!=', null)->count() > 0)
                <div class="flex gap-2 sm:ml-2">
                    <a href="{{ url('rooms/rooms/'.$room->id.'/A/'.$date->format('Y-m-d')) }}"
                       class="flex-1 sm:flex-none text-center px-4 py-1.5 rounded-xl text-xs font-semibold border {{ $week === 'A' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                        A-Woche
                    </a>
                    <a href="{{ url('rooms/rooms/'.$room->id.'/B/'.$date->format('Y-m-d')) }}"
                       class="flex-1 sm:flex-none text-center px-4 py-1.5 rounded-xl text-xs font-semibold border {{ $week === 'B' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                        B-Woche
                    </a>
                </div>
            @endif
        </div>

        {{-- Ladeindikator --}}
        <div id="loadingInfo" class="px-5 py-8 flex flex-col items-center gap-3 text-blue-600">
            <svg class="w-8 h-8 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 100 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"/>
            </svg>
            <span class="text-sm text-gray-500">Lade Buchungen...</span>
        </div>

        {{-- Wochentabelle --}}
        <div id="weekTableContainer" class="hidden">
            <div class="overflow-x-auto week-table-scroll">
                <table class="w-full border-collapse" style="min-width: 500px;" id="weekTable">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-xs font-medium text-gray-500 px-2 py-2 text-center border-r border-gray-200" style="width:52px;">Zeit</th>
                            @php
                                $days = [];
                                for($day = $startOfWeek->copy(); $day->lessThanOrEqualTo($endOfWeek); $day->addDay()) {
                                    $days[] = [
                                        'date'      => $day->format('Y-m-d'),
                                        'dayOfWeek' => $day->dayOfWeek,
                                        'name'      => config('config.days')[$day->dayOfWeek],
                                        'formatted' => $day->format('d.m.')
                                    ];
                                }
                            @endphp
                            @foreach($days as $day)
                                <th class="text-xs font-medium text-gray-700 px-1 py-2 text-center" data-date="{{ $day['date'] }}" data-weekday="{{ $day['dayOfWeek'] }}">
                                    <span class="hidden sm:inline">{{ $day['name'] }}<br></span>
                                    <span class="sm:hidden">{{ mb_substr($day['name'], 0, 2) }}<br></span>
                                    <span class="text-gray-400 font-normal">{{ $day['formatted'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="weekTableBody"></tbody>
                </table>
            </div>

            {{-- Legende --}}
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                <p class="text-xs font-medium text-gray-600 mb-2">Legende</p>
                <div class="flex flex-wrap gap-3 text-xs text-gray-600">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-blue-500 inline-block"></span> Wiederkehrend (zukünftig)
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-blue-300 inline-block"></span> Wiederkehrend (vergangen)
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-teal-500 inline-block"></span> Einzeltermin (zukünftig)
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-teal-300 inline-block"></span> Einzeltermin (vergangen)
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-amber-500 inline-block"></span> Vertretungsplan
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-violet-500 inline-block"></span> Indiware Stundenplan
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-green-400 inline-block" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.4) 2px, rgba(255,255,255,0.4) 4px);"></span> Raum frei durch VP
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-red-500 inline-block"></span> VP-Konflikt
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Kalender-Feed --}}
    @if($room->feed_url != "")
    <div class="mt-5 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 class="font-semibold text-gray-800 text-sm">Kalender-Feed</h3>
        </div>
        <div class="px-5 py-4">
            <p class="text-sm text-gray-600 mb-3">Die Raumbelegung kann in externen Kalenderanwendungen (z.B. Outlook, Google Calendar) abonniert werden.</p>
            <code class="block bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs text-gray-700 break-all">{{ $room->feed_url }}</code>
        </div>
    </div>
    @endif

</div>
</div>

<script>
    const bookings    = {!! $bookingsJson !!};
    const currentWeek = '{{ $week }}';
    const startTime   = '{{ config('rooms.start_booking') }}';
    const endTime     = '{{ config('rooms.end_booking') }}';
    const editUrl     = '{{ url('rooms/booking') }}';

    // ─── Tab-Umschalter ────────────────────────────────────────────────────────
    function switchTab(tab) {
        ['recurring', 'single'].forEach(t => {
            document.getElementById('pane-' + t).classList.toggle('hidden', t !== tab);
            const btn = document.getElementById('tab-' + t);
            btn.classList.toggle('bg-white', t === tab);
            btn.classList.toggle('shadow-sm', t === tab);
            btn.classList.toggle('text-blue-700', t === tab);
            btn.classList.toggle('text-gray-600', t !== tab);
        });
    }

    // ─── Hilfsfunktionen ───────────────────────────────────────────────────────
    function timeToMinutes(time) {
        const [h, m] = time.split(':').map(Number);
        return h * 60 + m;
    }
    function isPastTime(date, time) {
        return new Date(date + ' ' + time) < new Date();
    }
    function formatTime(t) { return t.substring(0, 5); }
    function calculateDuration(start, end) {
        return Math.max(1, Math.ceil((timeToMinutes(formatTime(end)) - timeToMinutes(formatTime(start))) / 15));
    }

    function generateTimeSlots() {
        const slots = [];
        const start = timeToMinutes(startTime);
        const end   = timeToMinutes(endTime);
        for (let m = start; m <= end; m += 15) {
            slots.push(String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'));
        }
        return slots;
    }

    function findBookingForSlot(weekday, date, time) {
        const cur  = timeToMinutes(time);
        const next = cur + 15;
        let found  = null;
        for (const b of bookings) {
            let applies = false;
            if (b.is_recurring) {
                if (b.weekday == weekday && (!b.week || b.week === currentWeek)) applies = true;
            } else {
                if (b.date === date) applies = true;
            }
            if (!applies) continue;
            const bs = timeToMinutes(formatTime(b.start));
            const be = timeToMinutes(formatTime(b.end));
            if ((bs >= cur && bs < next) || (bs < cur && be > cur)) {
                if (!b.is_recurring) return b;
                if (!found) found = b;
            }
        }
        return found;
    }

    // ─── Tabelle rendern ───────────────────────────────────────────────────────
    function renderWeekTable() {
        const tbody     = document.getElementById('weekTableBody');
        const timeSlots = generateTimeSlots();
        const headers   = document.querySelectorAll('th[data-date]');
        const days      = Array.from(headers).map(th => ({ date: th.dataset.date, weekday: parseInt(th.dataset.weekday) }));
        const occupied  = {};

        tbody.innerHTML = '';

        timeSlots.forEach((time, ti) => {
            const row = document.createElement('tr');
            row.className = 'border-b border-gray-100';

            // Zeitspalte
            const tc = document.createElement('td');
            tc.className = 'text-center text-gray-400 border-r border-gray-200 px-1';
            tc.style.cssText = 'font-size:0.65rem; width:52px; min-width:52px;';
            tc.textContent = time;
            row.appendChild(tc);

            days.forEach((day, di) => {
                if (occupied[`${di}-${ti}`]) return;

                const booking = findBookingForSlot(day.weekday, day.date, time);
                if (booking) {
                    const bs      = timeToMinutes(formatTime(booking.start));
                    const cur     = timeToMinutes(time);
                    const isFirst = bs >= cur && bs < cur + 15;

                    if (isFirst) {
                        const duration = calculateDuration(booking.start, booking.end);
                        const isPast   = isPastTime(day.date, time);
                        const cell     = document.createElement('td');
                        cell.rowSpan   = duration;

                        // Farbgebung nach Buchungstyp (source/cancelled)
                        let bg, ring, cellTitle;
                        const source    = booking.source || 'manual';
                        const cancelled = booking.cancelled || false;

                        if (source === 'indiware_vp') {
                            if (cancelled) {
                                // Stornierung durch VP (Raum frei)
                                bg        = 'bg-green-100 text-green-800';
                                ring      = 'ring-1 ring-green-400';
                                cellTitle = 'Raum frei durch Vertretungsplan';
                                cell.style.backgroundImage = 'repeating-linear-gradient(45deg, transparent, transparent 3px, rgba(74,222,128,0.2) 3px, rgba(74,222,128,0.2) 6px)';
                            } else {
                                // VP-Buchung (z.B. Vertretung, Neu, Verlegt)
                                bg        = isPast ? 'bg-amber-100 text-amber-800' : 'bg-amber-500 text-white';
                                ring      = 'ring-1 ring-amber-400';
                                cellTitle = 'Vertretungsplan-Buchung';
                            }
                        } else if (source === 'indiware_xml') {
                            // Indiware Stundenplan-Import
                            bg        = isPast ? 'bg-violet-100 text-violet-800' : 'bg-violet-500 text-white';
                            ring      = 'ring-1 ring-violet-400';
                            cellTitle = 'Indiware Stundenplan';
                        } else if (!booking.is_recurring) {
                            bg        = isPast ? 'bg-teal-100 text-teal-800' : 'bg-teal-500 text-white';
                            ring      = 'ring-1 ring-teal-400';
                            cellTitle = '';
                        } else {
                            bg        = isPast ? 'bg-blue-100 text-blue-700' : 'bg-blue-500 text-white';
                            ring      = 'ring-1 ring-blue-400';
                            cellTitle = '';
                        }

                        cell.className = `booking-cell px-1 py-0.5 align-top ${bg} ${ring} rounded-sm`;
                        cell.style.cursor = (source === 'indiware_vp' || source === 'indiware_xml') ? 'default' : 'pointer';
                        if (cellTitle) cell.title = cellTitle;
                        if (source !== 'indiware_vp' && source !== 'indiware_xml') {
                            cell.onclick = () => { window.location.href = `${editUrl}/${booking.id}`; };
                        }

                        const nameDiv = document.createElement('div');
                        nameDiv.style.cssText = 'font-size:0.7rem; font-weight:600; line-height:1.2; word-break:break-word;';
                        nameDiv.textContent = booking.name;
                        cell.appendChild(nameDiv);

                        // Klassen & Lehrer anzeigen (falls vorhanden)
                        const anzeigeName = booking.lehrer_name || booking.lehrer;
                        if (booking.klassen || anzeigeName) {
                            const metaDiv = document.createElement('div');
                            metaDiv.style.cssText = 'font-size:0.6rem; opacity:0.9; line-height:1.3; word-break:break-word; margin-top:1px;';
                            const parts = [];
                            if (booking.klassen) parts.push(booking.klassen);
                            if (anzeigeName) parts.push(anzeigeName);
                            metaDiv.textContent = parts.join(' · ');
                            // Kürzel als Tooltip wenn aufgelöster Name vorhanden
                            if (booking.lehrer_name && booking.lehrer && booking.lehrer_name !== booking.lehrer) {
                                metaDiv.title = 'Lehrer-Kürzel: ' + booking.lehrer;
                            }
                            cell.appendChild(metaDiv);
                        }

                        const timeSpan = document.createElement('div');
                        timeSpan.style.cssText = 'font-size:0.6rem; opacity:0.85;';
                        timeSpan.textContent = `${formatTime(booking.start)}–${formatTime(booking.end)}`;
                        cell.appendChild(timeSpan);

                        if (!booking.is_recurring) {
                            const badge = document.createElement('span');
                            badge.style.cssText = 'display:inline-block; font-size:0.55rem; padding:1px 4px; border-radius:4px; background:rgba(255,255,255,0.25); margin-top:2px;';
                            badge.textContent = booking.author || 'Einzeltermin';
                            cell.appendChild(badge);
                        }

                        row.appendChild(cell);
                        for (let i = 1; i < duration; i++) occupied[`${di}-${ti + i}`] = true;
                    }
                } else {
                    const cell = document.createElement('td');
                    const isPast = isPastTime(day.date, time);
                    cell.className = isPast ? 'bg-gray-50' : '';
                    cell.style.height = '10px';
                    row.appendChild(cell);
                }
            });

            tbody.appendChild(row);
        });

        document.getElementById('loadingInfo').style.display  = 'none';
        document.getElementById('weekTableContainer').classList.remove('hidden');
    }

    document.addEventListener('DOMContentLoaded', renderWeekTable);
</script>
@endsection

