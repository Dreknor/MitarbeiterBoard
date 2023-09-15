@php
    setlocale(LC_TIME, 'German');
    setlocale(LC_TIME, 'de_DE.utf8');


@endphp
<html>
<header>
    <meta charset="utf-8">

    <!-- CSS Files -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet"/>
    <link href="{{asset('css/paper-dashboard.css?v=2.0.0')}}" rel="stylesheet"/>
    <link href="{{asset('css/palette-gradient.css')}}" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet"/>
    <style>
        #leftBox {
            width: 49%;
            float: left;
            border-collapse: collapse;
        }

        #rightBox {
            width: 35%;
            float: right;
            border-collapse: collapse;
            margin-right: 90px;

        }

        body {
            font-size: 1em;
            margin-left: 10mm;
        }

        table {
            border-collapse: collapse;
            border: 1px solid black;

            width: 100%;
        }

        tr {
            border: 1px solid black;
            border-top: 0;
        }

        th {
            font-size: 1.0em;
            padding: 6px;
        }

        td {
            font-size: 0.9em;
            padding: 6px;
            text-align: center;
        }

        #logo {
            position: absolute;
            top: 0.0cm;
            right: 0.5cm;
            max-height: 50px;
            float: left;
        }
    </style>
</header>
<body>
<div class="container-fluid">
    <div style="width: 75%;">
        <div class="card border">
            <div class="card-header">
                <h6>
                    Arbeitszeitnachweis {{$month->month}}/{{$month->year}}
                </h6>
                <p>
                    {{$employe->vorname}} {{$employe->familienname}}
                </p>
            </div>
        </div>

    </div>
    <div style="width: 25%;">
        <img src="{{asset('img/'.config('app.logo'))}}" class=" pull-right" id="logo">
    </div>


    <div class="row">
        <table>
            <tr style="padding: 0px;">
                <th colspan="2">
                    Datum
                </th>
                <th>
                    Arbeitszeiten
                </th>
                <th>
                    Pause
                </th>
                <th>
                    Gesamt-Std.
                </th>
                <th>
                    Bemerkungen
                </th>
                <th>
                    Std.Konto
                </th>
            </tr>
            @for($day = $month->copy()->startOfMonth(); $day->lessThanOrEqualTo($month->copy()->endOfMonth()); $day->addDay())
                <tr style="padding: 0px;">
                    <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                        {{$day->dayName}}
                    </td>
                    <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                        {{$day->format('d.m.Y')}}
                    </td>
                    <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                        @foreach($timesheet_days->filterDay($day) as $timesheet_day)
                            @if(!is_null($timesheet_day->start))
                                <div class="row">
                                    <div class="col">
                                        {{$timesheet_day?->start?->format('H:i')}}
                                        - {{$timesheet_day?->end?->format('H:i')}} Uhr
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </td>
                    <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                        @if($timesheet_days->filterDay($day)->sum('pause') > 0)
                            {{$timesheet_days->filterDay($day)->sum('pause')}} Min
                        @endif
                    </td>
                    <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                        @if(!$day->isWeekday() or is_holiday($day))
                            @if($timesheet_days->filterDay($day)->sum('duration') > 0 )
                                {{convertTime($timesheet_days->filterDay($day)->sum('duration'))}} h
                            @endif
                        @else
                            {{convertTime($timesheet_days->filterDay($day)->sum('duration'))}}
                            h @if($day->isWeekday() and !is_holiday($day))
                                / {{convertTime(percent_to_seconds($employe->employments_date($day)->sum('percent'))/5)}}
                                h
                            @endif

                        @endif

                    </td>
                    <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                        @foreach($timesheet_days->filterDay($day) as $timesheet_day)
                            <div class="row">
                                <div class="col">
                                    {{\Illuminate\Support\Str::limit($timesheet_day?->comment, 15)}}
                                </div>
                            </div>
                        @endforeach
                        @if(is_holiday($day))
                            {{is_holiday($day)->title}}
                        @endif
                    </td>
                    <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;"
                        @endif  class="@if($timesheet_days->filterDay($day)->sum('duration')-percent_to_seconds($employe->employments_date($day)->sum('percent'))/5 > 0) text-success @else text-danger @endif">
                        @if($day->isWeekday() and !is_holiday($day))
                            {{convertTime($timesheet_days->filterDay($day)->sum('duration')-percent_to_seconds($employe->employments_date($day)->sum('percent'))/5)}}
                        @else
                            @if($timesheet_days->filterDay($day)->sum('duration') > 0)
                                {{convertTime($timesheet_days->filterDay($day)->sum('duration'))}}
                            @endif
                        @endif
                    </td>
                </tr>

            @endfor
        </table>
    </div>

    <div id="rightBox" style="height: 150px; margin-top: 15px">
        <div>
            <div class="leftBox" style="margin-top: 30px">
                <div class="border-bottom ">
                    Unterschrift Mitarbeiter:
                </div>
            </div>
            <div class="rightBox" style="margin-top: 40px">
                <div class="border-bottom ">
                    Unterschrift Leitung:
                </div>
            </div>
        </div>
    </div>
    <div id="leftBox" class="" style="height: 150px; margin-top: 15px">
        <table class="w-100 table-bordered">
            <tr>
                <th>
                    Stundenkonto alt:
                </th>
                <td>
                    {{convertTime($timesheet_old?->working_time_account)}} h
                </td>
            </tr>
            <tr>
                <th>
                    Stundenkonto neu:
                </th>
                <td>
                    {{convertTime($timesheet->working_time_account)}} h
                    ({{convertTime($timesheet->working_time_account - $timesheet_old?->working_time_account)}} h)
                </td>
            </tr>
            <tr>
                <th>
                    Urlaub bisher:
                </th>
                <td>
                    {{$timesheet->holidays_old}}
                </td>
            </tr>
            <tr>
                <th>
                    Urlaub neu:
                </th>
                <td>
                    {{$timesheet->holidays_new}}
                </td>
            </tr>
            <tr>
                <th>
                    Urlaub Rest:
                </th>
                <td>
                    {{$timesheet->holidays_rest}}
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
