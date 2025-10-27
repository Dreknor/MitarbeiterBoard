@extends('layouts.app')

@section('content')
    <a href="{{url('rooms/rooms')}}" class="btn btn-primary btn-link" >zurück</a>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <h5>
                        {{$room->name}} ({{$room->room_number}})
                    </h5>
                    <a class="btn btn-bg-gradient-x-blue-green" data-toggle="collapse" href="#createForm" role="button">
                        neue Reservierung
                    </a>
                </div>
            </div>
            <div class="card-body collapse" id="createForm">
                <ul class="nav nav-tabs" id="bookingTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="recurring-tab" data-toggle="tab" href="#recurring" role="tab">
                            Wiederkehrende Buchung
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="single-tab" data-toggle="tab" href="#single" role="tab">
                            Einzeltermin
                        </a>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="bookingTabContent">
                    <!-- Wiederkehrende Buchung -->
                    <div class="tab-pane fade show active" id="recurring" role="tabpanel">
                        <form method="post" action="{{url('rooms/bookings/')}}" class="form-horizontal">
                            @csrf
                            <input type="hidden" name="room_id" value="{{$room->id}}">
                            <input type="hidden" name="is_recurring" value="1">
                            <div class="form-row">
                                <div class="col-sm-3 col-md-3 col-lg-2">
                                    <label>Wochentag(e)</label>
                                    <select name="weekdays[]" id="weekday" class="custom-select" required multiple>
                                        @foreach(config('config.days') as $key => $day)
                                            <option value="{{$key}}"  @if (old('weekday') == $key) selected @endif>{{$day}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-3 col-md-3 col-lg-2">
                                    <label>Start</label>
                                    <input type="time" name="start" class="form-control p-2" required
                                           min="{{config('rooms.start_booking')}}"
                                           max="{{config('rooms.end_booking')}}"
                                           step="300"
                                           value="{{old('start')}}">
                                </div>
                                <div class="col-sm-3 col-md-3 col-lg-2">
                                    <label>Ende</label>
                                    <input type="time" name="end" class="form-control p-2" required
                                             min="{{config('rooms.start_booking')}}"
                                             max="{{config('rooms.end_booking')}}"
                                           step="300"
                                           value="{{old('end')}}">
                                </div>
                                <div class="col-sm-3 col-md-2 col-lg-2">
                                    <label>Woche</label>
                                    <select name="week" id="week" class="custom-select">
                                        <option selected> Jede </option>
                                        <option value="A" @if (old('week') == 'A') selected @endif>A-Woche</option>
                                        <option value="B" @if (old('week') == 'B') selected @endif>B-Woche</option>
                                    </select>
                                </div>
                                <div class="col-sm-12 col-md-8 col-lg-3">
                                    <label>Bezeichnung</label>
                                    <input type="text" maxlength="60" name="name" class="form-control p-2" required value="{{old('name')}}">
                                </div>
                                <div class="col-sm-12 col-md-2 col-lg-1">
                                    <button type="submit" class="mt-4 btn btn-block btn-bg-gradient-x-blue-green">
                                        <i class="fa fa-save"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Einzeltermin -->
                    <div class="tab-pane fade" id="single" role="tabpanel">
                        <form method="post" action="{{url('rooms/bookings/')}}" class="form-horizontal">
                            @csrf
                            <input type="hidden" name="room_id" value="{{$room->id}}">
                            <input type="hidden" name="is_recurring" value="0">
                            <div class="form-row">
                                <div class="col-sm-3 col-md-3 col-lg-2">
                                    <label>Datum</label>
                                    <input type="date" name="booking_date" class="form-control p-2" required value="{{old('booking_date', $date->format('Y-m-d'))}}">
                                </div>
                                <div class="col-sm-3 col-md-3 col-lg-2">
                                    <label>Start</label>
                                    <input type="time" name="start" class="form-control p-2" required
                                             min="{{config('rooms.start_booking')}}"
                                                max="{{config('rooms.end_booking')}}"
                                           step="300"
                                           value="{{old('start')}}">
                                </div>
                                <div class="col-sm-3 col-md-3 col-lg-2">
                                    <label>Ende</label>
                                    <input type="time" name="end" class="form-control p-2" required
                                                min="{{config('rooms.start_booking')}}"
                                                    max="{{config('rooms.end_booking')}}"
                                           step="300"
                                           value="{{old('end')}}">
                                </div>
                                <div class="col-sm-12 col-md-9 col-lg-5">
                                    <label>Bezeichnung</label>
                                    <input type="text" maxlength="60" name="name" class="form-control p-2" required value="{{old('name')}}">
                                </div>
                                <div class="col-sm-12 col-md-2 col-lg-1">
                                    <button type="submit" class="mt-4 btn btn-block btn-bg-gradient-x-blue-green">
                                        <i class="fa fa-save"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Wochennavigation -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <a href="{{url('rooms/rooms/'.$room->id.'/'.$week.'/'.$startOfWeek->copy()->subWeek()->format('Y-m-d'))}}"
                           class="btn btn-block btn-outline-primary">
                            <i class="fa fa-chevron-left"></i> Vorherige Woche
                        </a>
                    </div>
                    <div class="col-md-4 text-center">
                        <h6 class="mt-2">
                            KW {{$startOfWeek->weekOfYear}}
                            ({{$startOfWeek->format('d.m.')}} - {{$endOfWeek->format('d.m.Y')}})
                            @if($room->bookings()->where('week', '!=', null)->count() > 0)
                                <span class="badge badge-info">{{$week}}-Woche</span>
                            @endif
                        </h6>
                    </div>
                    <div class="col-md-4">
                        <a href="{{url('rooms/rooms/'.$room->id.'/'.$week.'/'.$startOfWeek->copy()->addWeek()->format('Y-m-d'))}}"
                           class="btn btn-block btn-outline-primary">
                            Nächste Woche <i class="fa fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                @if($room->bookings()->where('week', '!=', null)->count() > 0)
                    <div class="row mb-2">
                        <div class="col-6">
                            <a href="{{url('rooms/rooms/'.$room->id.'/A/'.$date->format('Y-m-d'))}}"
                               class="btn btn-sm btn-block @if($week == 'A') btn-bg-gradient-x-blue-green @else btn-outline-secondary @endif">
                               A-Woche
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{url('rooms/rooms/'.$room->id.'/B/'.$date->format('Y-m-d'))}}"
                               class="btn btn-sm btn-block @if($week == 'B') btn-bg-gradient-x-blue-green @else btn-outline-secondary @endif">
                                B-Woche
                            </a>
                        </div>
                    </div>
                @endif

                <div class="alert alert-info" id="loadingInfo">
                    <i class="fa fa-spinner fa-spin"></i> Lade Buchungen...
                </div>

                <div class="table-responsive" id="weekTableContainer" style="display: none;">
                    <table class="table table-bordered table-sm" style="table-layout: fixed;">
                        <thead>
                        <tr>
                            <th style="width: 60px;">Zeit</th>
                            @php
                                $days = [];
                                for($day = $startOfWeek->copy(); $day->lessThanOrEqualTo($endOfWeek); $day->addDay()) {
                                    $days[] = [
                                        'date' => $day->format('Y-m-d'),
                                        'dayOfWeek' => $day->dayOfWeek,
                                        'name' => config('config.days')[$day->dayOfWeek],
                                        'formatted' => $day->format('d.m.')
                                    ];
                                }
                            @endphp
                            @foreach($days as $day)
                                <th class="text-center" style="width: calc((100% - 60px) / 7);" data-date="{{$day['date']}}" data-weekday="{{$day['dayOfWeek']}}">
                                    {{$day['name']}}<br>
                                    <small>{{$day['formatted']}}</small>
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody id="weekTableBody">
                            <!-- Wird via JavaScript befüllt -->
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <h6>Legende:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Wiederkehrende Buchungen:</strong><br>
                            <span class="badge bg-gradient-radial-blue text-white">Zukünftig</span>
                            <span class="badge bg-gradient-x-light-blue text-white">Vergangen</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Einzeltermine:</strong><br>
                            <span class="badge bg-gradient-x-teal text-white">Zukünftig</span>
                            <span class="badge bg-gradient-x-teal-light text-white">Vergangen</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Buchungsdaten vom Server
        const bookings = {!! $bookingsJson !!};
        const currentWeek = '{{$week}}';
        const startTime = '{{config('rooms.start_booking')}}';
        const endTime = '{{config('rooms.end_booking')}}';
        const editUrl = '{{url('rooms/booking')}}';

        // Hilfsfunktion: Zeit in Minuten umrechnen
        function timeToMinutes(time) {
            const [hours, minutes] = time.split(':').map(Number);
            return hours * 60 + minutes;
        }

        // Hilfsfunktion: Prüft ob aktuelle Zeit in der Vergangenheit liegt
        function isPastTime(date, time) {
            const now = new Date();
            const checkDateTime = new Date(date + ' ' + time);
            return checkDateTime < now;
        }

        // Erstelle Zeitraster (15-Minuten-Schritte)
        function generateTimeSlots() {
            const slots = [];
            const start = timeToMinutes(startTime);
            const end = timeToMinutes(endTime);

            for (let minutes = start; minutes <= end; minutes += 15) {
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                const timeStr = String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
                slots.push(timeStr);
            }

            return slots;
        }

        // Formatiere Zeit ohne Sekunden (H:i Format)
        function formatTime(timeString) {
            // Entferne Sekunden falls vorhanden (HH:MM:SS -> HH:MM)
            return timeString.substring(0, 5);
        }


        function findBookingForSlot(weekday, date, time) {
            const currentMinutes = timeToMinutes(time);
            const nextSlotMinutes = currentMinutes + 15;
            let foundBooking = null;

            for (let booking of bookings) {
                // Prüfe ob Buchung auf diesen Tag zutrifft
                let applies = false;

                if (booking.is_recurring) {
                    // Wiederkehrende Buchung
                    if (booking.weekday == weekday) {
                        if (!booking.week || booking.week === currentWeek) {
                            applies = true;
                        }
                    }
                } else {
                    // Einzeltermin
                    if (booking.date === date) {
                        applies = true;
                    }
                }

                if (!applies) continue;

                // Prüfe Zeitbereich
                const bookingStart = timeToMinutes(formatTime(booking.start));
                const bookingEnd = timeToMinutes(formatTime(booking.end));

                const startsInSlot = bookingStart >= currentMinutes && bookingStart < nextSlotMinutes;
                const isRunning = bookingStart < currentMinutes && bookingEnd > currentMinutes;

                if (startsInSlot || isRunning) {
                    // Einzeltermine haben Vorrang vor wiederkehrenden Terminen
                    if (!booking.is_recurring) {
                        return booking;
                    }

                    // Wiederkehrende Buchung als Fallback speichern
                    if (!foundBooking) {
                        foundBooking = booking;
                    }
                }
            }

            return foundBooking;
        }

        // Berechne Dauer in 15-Minuten-Slots
        function calculateDuration(start, end) {
            const duration = (timeToMinutes(formatTime(end)) - timeToMinutes(formatTime(start))) / 15;
            return Math.max(1, Math.ceil(duration)); // Mindestens 1 Slot
        }

        // Rendere die Wochentabelle
        function renderWeekTable() {
            const tbody = document.getElementById('weekTableBody');
            const timeSlots = generateTimeSlots();
            const headers = document.querySelectorAll('th[data-date]');

            // Sammle Tag-Informationen
            const days = Array.from(headers).map(th => ({
                date: th.dataset.date,
                weekday: parseInt(th.dataset.weekday)
            }));

            // Tracking welche Zellen bereits belegt sind (rowspan)
            const occupiedCells = {};

            tbody.innerHTML = '';

            timeSlots.forEach((time, timeIndex) => {
                const row = document.createElement('tr');

                // Zeit-Spalte
                const timeCell = document.createElement('td');
                timeCell.className = 'text-center p-1';
                timeCell.style.fontSize = '0.75rem';
                timeCell.textContent = time;
                row.appendChild(timeCell);

                // Tag-Spalten
                days.forEach((day, dayIndex) => {
                    const cellKey = `${dayIndex}-${timeIndex}`;

                    // Überspringe, wenn Zelle von rowspan belegt
                    if (occupiedCells[cellKey]) {
                        return;
                    }

                    const booking = findBookingForSlot(day.weekday, day.date, time);

                    // Prüfe ob dies der Start einer Buchung ist
                    if (booking) {
                        const bookingStart = timeToMinutes(formatTime(booking.start));
                        const currentMinutes = timeToMinutes(time);
                        const nextSlotMinutes = currentMinutes + 15;

                        // Prüfe ob dies der erste Slot ist, der von dieser Buchung betroffen ist
                        // Ein Termin wird im ersten Slot angezeigt, in dem er beginnt
                        // d.h. wenn die Buchung zwischen currentMinutes und currentMinutes+15 startet
                        const isFirstSlot = bookingStart >= currentMinutes && bookingStart < nextSlotMinutes;

                        // Nur rendern wenn wir am ersten betroffenen Slot sind
                        if (isFirstSlot) {
                            // Start einer Buchung
                            const cell = document.createElement('td');
                            const duration = calculateDuration(booking.start, booking.end);
                            const isPast = isPastTime(day.date, time);

                            cell.rowSpan = duration;

                            // Farbgebung je nach Typ und Zeitpunkt
                            let colorClass;
                            if (!booking.is_recurring) {
                                // Individuelle Termine - Türkis
                                colorClass = isPast ? 'bg-gradient-x-teal-light' : 'bg-gradient-x-teal';
                            } else {
                                // Wiederkehrende Termine - Blau
                                colorClass = isPast ? 'bg-gradient-x-light-blue' : 'bg-gradient-radial-blue';
                            }

                            cell.className = `text-center p-1 text-white ${colorClass}`;
                            cell.style.cursor = 'pointer';
                            cell.onclick = () => window.location.href = `${editUrl}/${booking.id}`;

                            const nameDiv = document.createElement('div');
                            nameDiv.style.fontSize = '0.85rem';
                            nameDiv.style.fontWeight = 'bold';
                            nameDiv.textContent = booking.name;
                            cell.appendChild(nameDiv);

                            const timeSmall = document.createElement('small');
                            timeSmall.style.fontSize = '0.7rem';
                            timeSmall.textContent = `${formatTime(booking.start)} - ${formatTime(booking.end)}`;
                            cell.appendChild(timeSmall);

                            if (!booking.is_recurring) {
                                const br = document.createElement('br');
                                cell.appendChild(br);
                                const badge = document.createElement('span');
                                badge.className = 'badge badge-light';
                                badge.textContent = 'Einzeltermin';
                                cell.appendChild(badge);

                                const authorBadge = document.createElement('span');
                                authorBadge.className = 'badge badge-light ml-1';
                                authorBadge.textContent = booking.author;
                                cell.appendChild(authorBadge);

                            }

                            row.appendChild(cell);

                            // Markiere nachfolgende Zellen als belegt
                            for (let i = 1; i < duration; i++) {
                                occupiedCells[`${dayIndex}-${timeIndex + i}`] = true;
                            }
                        }
                    } else {
                        // Leere Zelle
                        const cell = document.createElement('td');
                        const isPast = isPastTime(day.date, time);
                        cell.className = `p-0 ${isPast ? 'bg-light' : ''}`;
                        cell.style.height = '10px';
                        row.appendChild(cell);
                    }
                });

                tbody.appendChild(row);
            });

            // Verstecke Loading, zeige Tabelle
            document.getElementById('loadingInfo').style.display = 'none';
            document.getElementById('weekTableContainer').style.display = 'block';
        }

        // Initialisiere beim Laden der Seite
        document.addEventListener('DOMContentLoaded', function() {
            renderWeekTable();
        });
    </script>
@endsection

