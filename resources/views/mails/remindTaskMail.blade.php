<!DOCTYPE html>
<html>
<head>
    <title>Erinnerung ausstehende Aufgabe</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="https://mitarbeiter.esz-radebeul.de">MitarbeiterBoard</a> steht die Erledigung folgender Aufgabe zum {{$date}} an:
</p>
<p>
    Thema: {{$theme}}<br>
    Aufgabe: {{$task}}
</p>
<p>
    @if($group == true)
        Dies ist eine Gruppenaufgabe, daher kann nicht kontrolliert werden, ob einzelne Personen diese bereits erledigt haben.
    @else
        Erledigte Aufgaben können im <a href="https://mitarbeiter.esz-radebeul.de">MitarbeiterBoard</a>
    @endif

</p>
<p>
    <a href="https://mitarbeiter.esz-radebeul.de">mitarbeiter.esz-radebeul.de</a>
</p>

</body>
</html>
