<!DOCTYPE html>
<html>
<head>
    <title>Neuer Eintrag zu Ticket</title>
</head>
<body>
<h2>Ticket: {{ $ticket->title }}</h2>
<p>
    {{ $name }} hat am {{ $comment->created_at?->format('d.m.Y H:i') }} folgenden Eintrag hinzugefügt:
</p>
<div style="border-left: 3px solid #ccc; padding-left: 10px; margin: 10px 0;">
    {!! $comment->comment_html !!}
</div>
<p>
    <strong>Status:</strong> {{ $ticket->status_label }}
    @if($ticket->waiting_until)
        <br>
        Das Ticket wartet auf eine Rückmeldung bis {{ $ticket->waiting_until->format('d.m.Y') }}.
        Erfolgt bis dahin keine Antwort, wird das Ticket automatisch geschlossen.
    @endif
</p>
<p>
    <a href="{{ route('tickets.show', $ticket->id) }}">Ticket anzeigen und antworten</a>
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>
</body>
</html>
