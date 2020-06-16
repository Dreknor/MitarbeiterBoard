<!DOCTYPE html>
<html>
<head>
    <title>neue Aufgabe</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="https://mitarbeiter.esz-radebeul.de">MitarbeiterBoard</a> wurde  @if($group != true) dir @else einer deiner Gruppen @endif eine neue Aufgabe zugewiesen:
    <br><br>
</p>
<p>
    Thema: {{$theme}}<br>
    Aufgabe: {{$task}}<br>
    Bis: {{$date}}
</p>
<p>
    <br>
    <a href="https://mitarbeiter.esz-radebeul.de">mitarbeiter.esz-radebeul.de</a>
</p>

</body>
</html>
