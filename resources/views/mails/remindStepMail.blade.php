<!DOCTYPE html>
<html>
<head>
    <title>Erinnerung ausstehender Auftrag</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> steht die Erledigung folgender Aufträge an:
    <br><br>
</p>
@foreach($steps as $step)
    <p>
        Prozess: <a href="{{config('app.url')}}/procedure/{{$step['stepId']}}/start">{{$step['procedureName']}}</a><br>
        Aufgabe: {{$step['stepName']}}<br>
        Datum: {{$step['endDate']}}<br>
        bereits erledigt?: <a href="{{config('app.url')}}/procedure/step/{{$step['stepId']}}/done/mail">JA</a>

    </p>
@endforeach
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
