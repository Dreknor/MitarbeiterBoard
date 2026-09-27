<!DOCTYPE html>
<html>
<head>
    <title>neue Aufgabe</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> wurde @if($group != true) dir @else dir gemeinsam mit anderen @isset($groupname) ({{$groupname}}) @endisset @endif eine neue Aufgabe zugewiesen:
    <br><br>
</p>
<p>
    Thema: {{$theme}}<br>
    Aufgabe: {{$task}}<br>
    Bis: {{$date}}
</p>
@if(!empty($url))
    <p><a href="{{ $url }}">Zum Thema</a></p>
@endif
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
