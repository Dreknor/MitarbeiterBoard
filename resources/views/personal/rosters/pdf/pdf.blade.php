<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>


    <!-- Bootstrap core CSS -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet">
    <!-- Your custom styles (optional) -->
    <link href="{{asset('css/roster_print.css')}}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{asset('css/colors.css')}}">

    <link rel="stylesheet" type="text/css" href="{{asset('fonts/line-awesome/css/line-awesome.css')}}">

</head>
<body>
<div @class(['container-fluid'])>
    <table class="w-100 border">
        @for($day = $roster->start_date->copy(); $day->lessThanOrEqualTo($roster->start_date->endOfWeek()); $day->addDay())

            @if($day->dayOfWeek== 6)
                <tr class="new-page" style="min-height:20mm">
                    <th colspan="{{$employes->count() + 2}}">
                        Hinweise:
                    </th>
                </tr>
                <tr class="" style="min-height:20mm">
                    <td colspan="{{$employes->count() + 2}}">
                        @foreach($roster->news as $news)
                            <p>{{$news->news}}</p>
                        @endforeach
                    </td>
                </tr>
            @endif
            @if($day->isWeekday() or $working_times->where('date', $day)->count()>0)
                <tr class="day">
                <td class="date">
                    <div class="rotate">{{ $day->locale('de')->dayName }}<br>
                        <small>{{$day->format('d.')}} {{ $day->locale('de')->monthName }} {{$day->format('Y')}}</small>
                    </div>
                </td>
                <td class="time">
                    <table class="w-100">
                        <tr class="name">
                            <td>
                                &nbsp;
                            </td>
                        </tr>
                        <tr class="working_times">
                            <td>
                                &nbsp;
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <ul>
                                    @for($time=\Carbon\Carbon::parse($day->copy()->format('d.m.Y 8:00')); $time->format('H:i') < '14:30'; $time->addMinutes(30))
                                        <li @class(['leererTermin'])>
                                            {{$time->format('H:i')}}
                                        </li>
                                    @endfor
                                </ul>

                            </td>
                        </tr>
                        <tr>
                            <td>

                            </td>
                        </tr>
                    </table>

                </td>

                @foreach($employes as $employe)
                    <td class="employe" style="width: {{550/$employes->count()}}mm">
                        <table>
                            <tr class="name">
                                <td colspan="2">
                                    @if($employe->geburtstag?->isBirthday($day))
                                        <i class="la la-birthday-cake info" style="font-size: 25px;"></i>
                                    @endif

                                    {{$employe->vorname}}

                                    @if($employe->geburtstag?->isBirthday($day))
                                        <i class="la la-birthday-cake success" style="font-size: 25px;"></i>
                                    @endif
                                </td>
                            </tr>
                            <tr class="working_times">
                                <td>{{optional($working_times->searchWorkingTime($employe, $day)->first()?->start)->format('H:i')}}</td>
                                <td>{{optional($working_times->searchWorkingTime($employe, $day)->first()?->end)->format('H:i')}}</td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <ul>
                                        @for($time=\Carbon\Carbon::parse($day->copy()->format('d.m.Y 8:00')); $time->format('H:i') < '14:30'; $time->addMinutes(15))
                                            @if($events->searchRosterEvent($employe, $time)->count() > 0 and $events->searchRosterEvent($employe, $time)->first()->start == $time)
                                                <li @class(['Termin'])
                                                    @if($events->searchRosterEvent($employe, $time)->first()->end->lessThanOrEqualTo(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' 14:00')))
                                                    style="height: {{ ($events->searchRosterEvent($employe, $time)->first()->duration / 15) * 3 }}mm; "
                                                    @else
                                                    style="height: {{ ($events->searchRosterEvent($employe, $time)->first()->start->diffInMinutes(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $time->format('Y-m-d'). ' 14:30')) / 15) * 3 }}mm;"
                                                    @endif
                                                >
                                                    <div class="innerText">
                                                        {{$events->searchRosterEvent($employe, $time)->first()->event}}

                                                        @if($events->searchRosterEvent($employe, $time)->first()->end->format('H:i') > '14:30')
                                                            (bis {{$events->searchRosterEvent($employe, $time)->first()->end->format('H:i')}}
                                                            Uhr)
                                                        @endif
                                                    </div>
                                                </li>
                                            @elseif(!$events->searchRosterEvent($employe, $time)->count() > 0)
                                                <li @class(['leererTermin_'.$time->minute])>

                                                </li>
                                            @endif
                                        @endfor
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <td class="function " colspan="2">
                                    {{Str::limit(optional($working_times->searchWorkingTime($employe, $day)->first())->function, 180/$employes->count())}}
                                </td>
                            </tr>
                        </table>
                    </td>

                @endforeach
            </tr>
            @endif
        @endfor
    </table>

</div>
</body>
</html>
