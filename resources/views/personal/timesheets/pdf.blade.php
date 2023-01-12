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
        body {
            font-size: 11px;
        }
        th {
            font-size: 11px;
        }
        td {
            font-size: 10px;
        }
        #logo{
            position: absolute;
            top: 0.0cm;
            right: 0.5cm;
            max-height: 75px;
            float: left;
        }
    </style>
</header>
<body>
<div class="container-fluid">
        <img src="{{asset('img/'.config('app.logo'))}}" class=" pull-right" id="logo">
    <div class="card border w-50">
            <div class="col-auto">
                <div class="card-header">
                    <div class="row">
                        <div style="width: 50%">
                            <h6>
                                Arbeitszeitnachweis {{$month->month}}/{{$month->year}}
                            </h6>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            {{$employe->vorname}} {{$employe->familienname}}
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom border-top">
                    <div class="row">
                        <div class="col-auto">
                            Stundenkonto neu: {{convertTime($timesheet->working_time_account)}} h
                        </div>
                        <div class="col-auto">
                            Urlaub bisher: {{$timesheet->holidays_old}}
                        </div>
                        <div class="col-auto">
                            Urlaub neu: {{$timesheet->holidays_new}}
                        </div>
                        <div class="col-auto">
                            Urlaub Rest: {{$timesheet->holidays_rest}}
                        </div>
                    </div>
                </div>
            </div>
    </div>
        <div class="">
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
        <div class="">
            <div class="row">
                <div class="col">
                    <p>
                        __________________________________________
                    </p>
                </div>
                <div class="col">
                    <p>
                        __________________________________________
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <p>
                        Unterschrift Mitarbeiter
                    </p>
                </div>
                <div class="col">
                    <p>
                        Unterschrift Leitung
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
