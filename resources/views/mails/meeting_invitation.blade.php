@php
    use Carbon\Carbon;
@endphp

<p>Hallo {{ $user->name }},</p>

<p>du bist zum folgenden Meeting eingeladen:</p>

<ul>
    <li><strong>Titel:</strong> {{ $meeting->title }}</li>
    <li><strong>Datum:</strong> {{ $meeting->date->format('d.m.Y') }}</li>
    <li><strong>Uhrzeit:</strong> {{ $meeting->start_time }} - {{ $meeting->end_time }}</li>
    @if($group->meeting_url)
        <li><strong>Meeting-Link:</strong> <a href="{{ $group->meeting_url }}">{{ $group->meeting_url }}</a></li>
    @endif
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

@if($messageText)
    <p><strong>Zusätzliche Nachricht:</strong><br>{{ $messageText }}</p>
@endif

<p>Viele Grüße<br>
{{$absender}}
</p>

