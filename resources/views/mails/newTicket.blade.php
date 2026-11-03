<!DOCTYPE html>
<html>
<head>
    <title>Neues Ticket erstellt</title>
</head>
<body>
<h1>Neues Ticket erstellt</h1>
<p>Ein neues Ticket wurde mit den folgenden Details erstellt:</p>
<ul>
    <li><strong>Titel:</strong> {{ $ticket->title }}</li>
    <li><strong>Erstellt von:</strong> {{ $ticket->user?->name }}</li>
    <li><strong>Kategorie:</strong> {{ $ticket->category?->name ?? 'keine' }}</li>
    <li><strong>Priorität:</strong> {{ $ticket->priority_label }}</li>
</ul>
<div style="border-left: 3px solid #ccc; padding-left: 10px; margin: 10px 0;">
    {!! $ticket->description_html !!}
</div>
<p>
    <a href="{{ route('tickets.show', $ticket->id) }}">Ticket anzeigen</a>
</p>
</body>
</html>
