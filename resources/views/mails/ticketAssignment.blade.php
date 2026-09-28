<!DOCTYPE html>
<html>
<head>
    <title>Ticket zugewiesen</title>
</head>
<body>
<h1>Ticket zugewiesen: {{ $ticket->title }}</h1>
<p>Ihnen wurde ein Ticket mit folgenden Details zugewiesen:</p>
<ul>
    <li><strong>Titel:</strong> {{ $ticket->title }}</li>
    <li><strong>Erstellt von:</strong> {{ $ticket->user?->name }}</li>
    @if($ticket->category)
        <li><strong>Kategorie:</strong> {{ $ticket->category->name }}</li>
    @endif
    <li><strong>Priorität:</strong> {{ $ticket->priority_label }}</li>
    <li><strong>Status:</strong> {{ $ticket->status_label }}</li>
</ul>
<div style="border-left: 3px solid #ccc; padding-left: 10px; margin: 10px 0;">
    {!! $ticket->description_html !!}
</div>
@if($ticket->waiting_until)
    <p><strong>Warten bis:</strong> {{ $ticket->waiting_until->format('d.m.Y') }}</p>
@endif
<p>
    <a href="{{ route('tickets.show', $ticket->id) }}">Ticket anzeigen</a>
</p>
</body>
</html>
