<!DOCTYPE html>
<html>
<head>
    <title>Übersicht Abwesenheiten</title>
</head>
<body>
<p>Folgende Personen fehlen heute:</p>
<p>
@foreach($absences as $absence)
    {{$absence->user->name}} ({{$absence->reason}}): {{$absence->start->format('d.m.Y')}} - {{$absence->end->format('d.m.Y')}} <br>
@endforeach
</p>

</body>
</html>
