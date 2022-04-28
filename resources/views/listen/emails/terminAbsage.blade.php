<!DOCTYPE html>
<html>
<head>
    <title>Terminabsage</title>
</head>
<body>
<p>
    {{$user->name}} hat den Termin  am {{$termin->format('d.m.Y')}} um {{$termin->format('H:i')}} Uhr für {{$liste->listenname}} abgesagt.
</p>
@if(!empty($text))
    <p>
        Folgende Nachricht wurde angefügt:
    </p>
    <p>
        {!! $text !!}
    </p>
@endif
<p>
    Diese E-Mail wurde automatisch versandt.
</p>
</body>
</html>
