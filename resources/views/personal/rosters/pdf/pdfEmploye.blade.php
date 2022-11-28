<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>


    <!-- Bootstrap core CSS -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet">
    <link href="{{asset('css/bootstrap-extended.css')}}" rel="stylesheet">
    <!-- Your custom styles (optional) -->
    <link href="{{asset('css/roster_user_print.css')}}" rel="stylesheet">

</head>
<body>
<div @class(['container-fluid'])>
    <h6>
        Dienstplan vom {{$roster->start_date->format('d.m.')}} - {{$roster->start_date->endOfWeek()->format('d.m.Y')}}
        für {{ $employe->vorname }}
    </h6>
    <div class="row">
        <table class="roster-table">
            <tr class="day">
                <td class="time">
                    <table class="w-100">
                        <tr class="name">
                            <td>
                                Zeit
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
                                    @for($time=\Carbon\Carbon::parse($roster->start_date->copy()->format('d.m.Y 8:00')); $time->format('H:i') < '14:30'; $time->addMinutes(15))
                                        <li @class(['leererTermin_'.$time->minute])>
                                            @if($time->minute == 0 or $time->minute == 30)
                                                {{$time->format('H:i')}}
                                            @endif
                                        </li>
                                    @endfor
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td class="function">

                            </td>
                        </tr>
                    </table>
                </td>
                @for($day=$roster->start_date->copy(); $day->lessThan($roster->start_date->endOfWeek()); $day->addDay())
                    <td class="day">
                        <table class="w-100">
                            <tr class="name">
                                <th colspan="2">
                                    {{$day->locale('de')->dayName}}
                                </th>
                            </tr>
                            <tr class="working_times">
                                <td>{{optional($working_times->searchWorkingTime($employe, $day)->first()?->start)->format('H:i')}}</td>
                                <td>{{optional($working_times->searchWorkingTime($employe, $day)->first()?->end)->format('H:i')}}</td>
                            </tr>
                            <tr class="plan">
                                <td colspan="2">
                                    <ul>
                                        @for($time=\Carbon\Carbon::parse($day->copy()->format('d.m.Y 8:00')); $time->format('H:i') < '14:30'; $time->addMinutes(15))
                                            @if($events->searchRosterEvent($employe, $time)->count() > 0 and $events->searchRosterEvent($employe, $time)->first()->start == $time)
                                                <li @class(['Termin', 'border_'.$events->searchRosterEvent($employe, $time)->first()->end->minute  ])

                                                    @if($events->searchRosterEvent($employe, $time)->first()->end->lessThanOrEqualTo(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' 14:00')))
                                                        style="height: {{ (($events->searchRosterEvent($employe, $time)->first()->duration / 15) * 7) }}mm; "
                                                    @else
                                                        style="height: {{ ($events->searchRosterEvent($employe, $time)->first()->start->diffInMinutes(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $time->format('Y-m-d'). ' 14:30')) / 15) * 7 }}mm;"
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
                                <th colspan="2" class="function text-center">
                                    {{$working_times->searchWorkingTime($employe, $day)->first()?->function}}
                                </th>
                            </tr>
                        </table>
                    </td>
                @endfor
            </tr>
            <tr>
                <td colspan="8">
                    <ul>
                        @foreach($roster->news as $news)
                            <li>
                                {{$news->news}}
                            </li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        </table>

    </div>

</div>
</body>
</html>
