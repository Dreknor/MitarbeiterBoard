@php
    setlocale(LC_TIME, 'German');
    setlocale(LC_TIME, 'de_DE.utf8');


@endphp
<html>
<header>
    <meta charset="utf-8">

    <!-- CSS Files -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet" />
    <link href="{{asset('css/paper-dashboard.css?v=2.0.0')}}" rel="stylesheet" />
    <link href="{{asset('css/palette-gradient.css')}}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
    <style>
        #leftBox{
            width: 49%;
            float: left;
            border-collapse: collapse;
        }
        #rightBox{
            width: 50%;
            float: right;
            border-collapse: collapse;

        }
        body {
            font-size: 10px;
        }
        table {
            border-collapse: collapse;
        }
        tr {
            padding: 1px;
        }
        th {
            font-size: 10px;
            padding: 2px;
        }
        td {
            font-size: 8px;
            padding: 2px;
            text-align: center;
        }
        #logo{
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
        <div class="card border w-100">
            <div class="card-header">
                    <h6>
                        Arbeitszeitnachweis {{$month->month}}/{{$month->year}} - {{$employe->vorname}} {{$employe->familienname}}
                    </h6>
            </div>
        </div>
    </div>
    <div style="width: 75%;">
        <img src="{{asset('img/'.config('app.logo'))}}" class=" pull-right" id="logo">
    </div>


            <div class="row">
                <table class="table w-100 table-striped border table-bordered table-sm">
                <tr style="padding: 0px;">
                    <th>
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
                            {{$day->dayName}}, {{$day->format('d.m.Y')}}
                        </td>
                        <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                            @foreach($timesheet_days->filterDay($day) as $timesheet_day)
                                @if(!is_null($timesheet_day->start))
                                    <div class="row">
                                        <div class="col">
                                            {{$timesheet_day?->start?->format('H:i')}} - {{$timesheet_day?->end?->format('H:i')}} Uhr
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </td>
                        <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                            @if($timesheet_days->filterDay($day)->sum('pause') > 0)
                                {{$timesheet_days->filterDay($day)->sum('pause')}} Minuten
                            @endif
                        </td>
                        <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                            {{convertTime($timesheet_days->filterDay($day)->sum('duration'))}} h @if($day->isWeekday() and !is_holiday($day)) / {{convertTime(percent_to_minutes($employe->employments_date($day)->sum('percent'))/5)}} h @endif
                        </td>
                        <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif >
                            @foreach($timesheet_days->filterDay($day) as $timesheet_day)
                                <div class="row">
                                    <div class="col">
                                        {{$timesheet_day?->comment}}
                                    </div>
                                </div>
                            @endforeach
                            @if(is_holiday($day))
                                {{is_holiday($day)->title}}
                            @endif
                        </td>
                        <td @if(!$day->isWeekday() or is_holiday($day)) style="background-color: rgb(166,201,246) !important;" @endif  class="@if($timesheet_days->filterDay($day)->sum('duration')-percent_to_minutes($employe->employments_date($day)->sum('percent'))/5 > 0) text-success @else text-danger @endif">
                            @if($day->isWeekday() and !is_holiday($day))
                                {{convertTime($timesheet_days->filterDay($day)->sum('duration')-percent_to_minutes($employe->employments_date($day)->sum('percent'))/5)}}
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

    <div id="leftBox">
        <div style="width: 75%;">
            <div class="leftBox" style="margin-top: 20px">
                <div class="border-bottom ">
                    Unterschrift Mitarbeiter:
                </div>
            </div>
            <div class="rightBox" style="margin-top: 20px">
                <div class="border-bottom ">
                    Unterschrift Leitung:
                </div>
            </div>
        </div>
    </div>
    <div id="rightBox" class="border">
        <table class="w-100 table-bordered">
            <tr>
                <th ">
                    Stundenkonto neu:
                </th>
                <td style="text-align: left; font-size: 9px;">
                    {{convertTime($timesheet->working_time_account)}} h
                </td>
            </tr>
            <tr>
                <th >
                    Urlaub bisher:
                </th>
                <td style="text-align: left; font-size: 9px;">
                    {{$timesheet->holidays_old}}
                </td>
            </tr>
            <tr>
                <th >
                    Urlaub neu:
                </th>
                <td style="text-align: left; font-size: 9px;">
                    {{$timesheet->holidays_new}}
                </td>
            </tr>
            <tr>
                <th  >
                    Urlaub Rest:
                </th>
                <td style="text-align: left; font-size: 9px;">
                    {{$timesheet->holidays_rest}}
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
