<!DOCTYPE html>
<html>
<head>
    <title>Erinnerung ausstehender Auftrag</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> steht die Erledigung folgendes Auftrages an:
    <br><br>
</p>
<p>
    Prozess: <a href="{{config('app.url')}}/procedure/{{$procedureId}}/start">{{$procedure}}</a><br>
    Aufgabe: {{$step}}<br>
    Datum: {{$date}}
</p>
<p>
    Den Auftrag bereits erledigt? <a href="{{config('app.url')}}/procedure/step/{{$stepId}}/done/mail">JA</a>
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
