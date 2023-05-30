<!DOCTYPE html>
<html>
<head>
    <title>Erinnerung ausstehende Aufgaben</title>
</head>
<body>

<p>Liebe/r {{$name}}</p>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> steht die Erledigung folgender Aufgaben an:
    <br><br>
</p>
@foreach($tasks as $task)
    <p>
        Aufgabe: {{$task->task}} <br>
        Fällig: {{$task->date?->format('d.m.Y')}}<br>
        <a href="{{config('app.url').'/tasks/'.$task->id.'/complete'}}">bereits erledigt? Dann hier abhaken.</a>
    </p>
@endforeach
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
