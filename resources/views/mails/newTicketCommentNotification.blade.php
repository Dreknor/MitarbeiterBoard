<!DOCTYPE html>
<html>
<head>
    <title>Neuer Kommentar zu Ticket</title>
</head>
<body>
<h1>Neuer Kommentar zu Ticket: {{ $ticket->title }}</h1>
<p>{{ $comment->user->name }} hat einen neuen Kommentar hinzugefügt:</p>
<p>{!!  $comment->comment !!} </p>
@if($ticket->waiting_until)
    <p>
        Das Ticket wartet auf eine Antwort bis: {{ $ticket->waiting_until->format('d.m.Y H:i') }}
        <br> Sollte bis dahin keine Antwort erfolgen, wird das Ticket automatisch geschlossen.
    </p>
@endif
</body>
</html>
