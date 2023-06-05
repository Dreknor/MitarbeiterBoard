<!DOCTYPE html>
<html>
<head>
    <title>neuer Beitrag für Thema </title>
</head>
<body>

<p>
    {{$name}} hat ein neues Protokoll angelegt.
</p>
<p>
    Thema: <a href="{{config('app.url')."/$groupname/themes/$theme_id"}}">{{$theme}}</a> <br>
</p>
<p>
    Protokoll:<br>
    {!! $protocol !!}
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
