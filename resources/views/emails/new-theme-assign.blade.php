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
    Thema: <a href="{{config('app.url').$theme->group->name.'/themes'.'/'.$theme->id}}">{{$theme->theme}}</a> <br>
</p>
<p>
    Informationen:<br>
    {!! $theme->information !!}}
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
