<!DOCTYPE html>
<html>
<head>
    <title>Erinnerung ausstehende Aufgabe</title>
</head>
<body>

<p>Liebe/r {{$user->name}}</p>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> sind dir folgende Themen zugewiesen:
    <br><br>
</p>
@foreach($themes as $theme)
    <p>
        Thema: {{$theme->theme}}<br>
        erstellt: {{$theme->date->format('d.m.Y')}}
    </p>
@endforeach

<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
