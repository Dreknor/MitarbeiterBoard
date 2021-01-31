<!DOCTYPE html>
<html>
<head>
    <title>Erinnerung ausstehende Aufgabe</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> steht die Erledigung folgender Aufgabe zum {{$date}} an:
    <br><br>
</p>
<p>
    Thema: {{$theme}}<br>
    Aufgabe: {{$task}}
</p>
<p>
    @if($group == true)
        Dies ist eine Gruppenaufgabe, daher kann nicht kontrolliert werden, ob einzelne Personen diese bereits erledigt haben.
    @else
        Erledigte Aufgaben können im <a href="{{config('app.url')}}">{{config('app.name')}}</a> gekennzeichnet werden.
    @endif

</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
