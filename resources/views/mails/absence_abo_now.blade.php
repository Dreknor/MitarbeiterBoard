<!DOCTYPE html>
<html>
<head>
    <title>Abwesenheitsbenachrichtigung</title>
</head>
<body>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> wurde eine neue Abwesenheitsmitteilung angelegt:
    <br><br>
</p>
<p>
    Name: {{$username}}<br>
    Zeitraum: {{$start}} - {{$end}}<br>
    Grund: {{$reason}}
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
