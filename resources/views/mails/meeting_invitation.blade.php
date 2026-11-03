@php
    use Carbon\Carbon;
@endphp

<p>Hallo {{ $user->name }},</p>

@if($messageText)
    <p><strong>Zusätzliche Nachricht:</strong><br>{{ $messageText }}</p>
@endif

<p>du bist zum folgenden Meeting eingeladen:</p>
<ul>
    <li><strong>Titel:</strong> {{ $meeting->title }}</li>
    <li><strong>Datum:</strong> {{ $meeting->date->format('d.m.Y') }}</li>
    <li><strong>Uhrzeit:</strong> {{ $meeting->start_time }} - {{ $meeting->end_time }}</li>
    @if($meeting->roomBooking && $meeting->roomBooking->room)
        <li>
            <strong>Raum:</strong>
            {{ $meeting->roomBooking->room->name }}
            @if($meeting->roomBooking->room->room_number)
                (Nr. {{ $meeting->roomBooking->room->room_number }})
            @endif
        </li>
    @endif
    @if($meeting->location)
        <li><strong>Ort:</strong> {{ $meeting->location }}</li>
    @endif
    @if($meeting->effectiveMeetingUrl())
        <li><strong>Meeting-Link:</strong> <a href="{{ $meeting->effectiveMeetingUrl() }}">{{ $meeting->effectiveMeetingUrl() }}</a></li>
    @endif
    <li><strong>Kontext:</strong> {{ $meeting->contextLabel() }}</li>
    <li><strong>Themen:</strong>
        <ul>
            @forelse($meeting->themes as $theme)
                <li>{{ $theme->theme }} ({{ $theme->duration }} min)</li>
            @empty
                <li>Keine Themen festgelegt.</li>
            @endforelse
        </ul>
    </li>
</ul>
<p><a href="{{ route('meetings.show', $meeting) }}">Meeting im MitarbeiterBoard öffnen</a></p>
<p>Viele Grüße<br>
{{$absender}}
</p>

