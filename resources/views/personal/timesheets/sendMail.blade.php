<!DOCTYPE html>
<html>
<head>
    <title>Arbeitszeitübersicht</title>
</head>
<body>
<p>
    Liebe/r {{$user->name}},
</p>
<p>
    Bitte kontrolliere deine Arbeitszeitübersicht und ergänze fehlende Arbeitszeiten im <a href="{{config('app.url').'/timesheets/'.$user->id.'/'.$date}}">{{config('app.name')}}</a>.
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
