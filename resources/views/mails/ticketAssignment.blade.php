<!DOCTYPE html>
<html>
<head>
    <title>Ticket zugewiesen</title>
</head>
<body>
<h1>Ticket zugewiesen: {{ $ticket->title }}</h1>
<p>Ihnen wurde ein Ticket zugewiesen mit folgenden Details:</p>
<ul>
    <li><strong>Titel:</strong> {{ $ticket->title }}</li>
    <li><strong>Beschreibung:</strong> {!! $ticket->description !!}</li>
    <li><strong>Erstellt von:</strong> {{ $ticket->user->name }}</li>
    @if($ticket->category)
        <li><strong>Kategorie:</strong> {{ $ticket->category->name }}</li>
    @endif
    <li><strong>Priorität:</strong> {{ $ticket->priority }}</li>
</ul>
@if($ticket->waiting_until)
    <p><strong>Warten bis:</strong> {{ $ticket->waiting_until->format('d.m.Y H:i') }}</p>
@endif
<p>
    <a href="{{ url('/tickets/' . $ticket->id) }}">Ticket anzeigen</a>
</p>
</body>
</html>
