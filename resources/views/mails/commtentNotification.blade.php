<!DOCTYPE html>
<html>
<head>
    <title>neuer Kommentar für Ticket</title>
</head>
<body>

<p>
    {Für das Ticket {{$ticket->title}} wurde ein neuer Kommentar hinzugefügt.
</p>
<p>
    {{$comment->comment}}
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
