<!DOCTYPE html>
<html>
<head>
    <title>Prozessfortschritt - neuer Schritt</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> ist im Prozess {{$procedure}} dein handeln erforderlich:
    <br><br>
</p>
<p>
    Prozess: <a href="{{config('app.url')}}/procedure/{{$procedure_id}}/start">{{$procedure}}</a> <br>
    Schritt: {{$step}}<br>
    Bis: {{$date}}
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
