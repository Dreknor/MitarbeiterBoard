<!DOCTYPE html>
<html>
<head>
    <title>Übersicht neue Nachrichten</title>
</head>
<body>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> wurden folgende neue Mitteilungen veröffentlicht:
    <br><br>
</p>
<p>
    <ul>
        @foreach($posts as $post)
            <li>
                {{$post->header}}
            </li>
        @endforeach
    </ul>
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.url')}}</a>
</p>

</body>
</html>
