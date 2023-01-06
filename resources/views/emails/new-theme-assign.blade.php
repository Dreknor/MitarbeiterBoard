<!DOCTYPE html>
<html>
<head>
    <title>Themenzuweisung</title>
</head>
<body>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> wurde dir ein Thema zugewiesen:
    <br><br>
</p>
<p>
    Thema: <a href="{{config('app.url').'/themes/'.$theme->group->name.'/'.$theme->id}}">{{$theme->theme}}</a> <br>
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
