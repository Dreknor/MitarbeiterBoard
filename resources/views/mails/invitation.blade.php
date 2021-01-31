<!DOCTYPE html>
<html>
<head>
    <title>Einladung Treffen am {{$date}}</title>
</head>
<body>

<p>Liebes Team der {{$group}}</p>
<p>
    Für unser Treffen am {{$date}}, erhaltet ihr hier die Themen, die besprochen werden sollen.
    Loggt euch in unser MitarbeiterBoard ein, um die Themen zu priorisieren und euch vorab eine Meinung zu bilden.
</p>
<p>
    <ul>
    @foreach($themes as $theme)
        <li>{{$theme->theme}}</li>
    @endforeach
    </ul>
</p>
<p>
    Eventuell liegen auch noch weitere. ältere Themen vor, die jedoch nicht in dieser Mail erfasst wurden.
</p>
<p>
    <a href="{{config('app.url')}}">{{config('app.name')}}</a>
</p>

</body>
</html>
