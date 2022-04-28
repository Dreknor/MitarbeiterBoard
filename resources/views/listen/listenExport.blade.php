<!DOCTYPE html>
<html>
<head>
    <title>{{$Liste->listenname}}</title>

    <!-- CSS Files -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet" />
    <link href="{{asset('css/paper-dashboard.css?v=2.0.0')}}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />

    <link href="{{asset('/css/all.css')}}" rel="stylesheet"> <!--load all styles -->
</head>
<body>

<h2>
    {{$Liste->listenname}}
</h2>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>
                Datum
            </th>
            <th>
                Uhrzeit
            </th>
            <th>
                Familie
            </th>
            <th>
                Bemerkungen
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($listentermine as $eintrag)
            <tr>
                <td>
                    {{$eintrag->termin->format('d.m.Y')}}
                </td>
                <td>
                    {{	$eintrag->termin->format('H:i')}} - {{$eintrag->termin->copy()->addMinutes($Liste->duration)->format('H:i')}} Uhr
                </td>
                <td>
                    {{optional($eintrag->eingetragenePerson)->name }}
                </td>
                <td>
                    {{$eintrag->comment}}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
