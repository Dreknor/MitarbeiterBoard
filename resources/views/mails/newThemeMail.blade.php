<!DOCTYPE html>
<html>
<head>
    <title>neues Thema</title>
</head>
<body>

<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> wurde  einer deiner Gruppe {{$groupname}} ein neues Thema eröffnet:
    <br><br>
</p>
<p>
    Thema: <a href="{{config('app.url')."/$groupname/themes/$theme_id"}}">{{$theme}}</a> <br>
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.url')}}</a>
</p>

</body>
</html>
