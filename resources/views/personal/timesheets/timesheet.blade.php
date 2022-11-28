@extends('layouts.app')

@section('title')
    Arbeitszeitnachweis
@endsection

@section('site-title')
    Arbeitszeitnachweis
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card border">
            <div class="card-header">
                <h6>Arbeitszeitnachweis {{$employe->vorname}} {{$employe->familienname}}</h6>
                <p>
                    <div class="d-inline">
                    {{$month->monthName}} {{$month->year}}
                    </div>
                    <div class="d-inline pull-right">
                        <div class="dropleft">
                            <button class="btn btn-sm btn-bg-gradient-x-blue-green dropdown-toggle"  data-toggle="dropdown"  id="dropdownMenuButton_selectMonth">
                                <i class="fa fa-scroll"></i> {{$month->monthName}} {{$month->year}}
                            </button>
                            <ul class="dropdown-menu pre-scrollable">
                                @for($selectMonth = $employe->employments->sortBy('start')->first()->start; $selectMonth->lessThanOrEqualTo(\Carbon\Carbon::today()->endOfMonth()); $selectMonth->addMonth())
                                    <li>
                                        <a href="{{url('timesheets/'.$employe->id.'/'.$selectMonth->format('Y-m'))}}" class="dropdown-item text-info">
                                            {{$selectMonth->monthName}} {{$selectMonth->year}}
                                        </a>
                                    </li>

                                @endfor
                            </ul>
                        </div>
                    </div>
                </p>
            </div>
            <div class="card-body border-bottom border-top">
                <div class="row">
                    <div class="col-md-12 col-lg-6">
                        <p>
                            <b>
                                letzter Monat:
                            </b>
                        </p>
                        <p>
                            @if($timesheet_old)
                                <p>
                                    {{convertTime($timesheet_old->working_time_account)}}
                                </p>
                            @else
                                kein Arbeitszeitnachweis vorhanden
                            @endif
                        </p>
                    </div>
                    <div class="col-md-12 col-lg-6">
                        <p>
                            <b>
                                dieser Monat:
                            </b>
                        </p>
                        <p>
                            @if($timesheet)
                                <div class="row">
                                    <div class="col-auto">
                                        Stundenkonto: {{convertTime($timesheet->working_time_account)}} h
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

                            @else
                                kein Arbeitszeitnachweis gespeichert
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <table class="table w-100 table-striped border table-bordered">
                    <tr>
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
                        <th>

                        </th>
                    </tr>
                    @for($day = $month->copy()->startOfMonth(); $day->lessThanOrEqualTo($month->copy()->endOfMonth()); $day->addDay())
                        @include('personal.timesheets.day')
                    @endfor
                </table>
            </div>
        </div>
    </div>
@endsection
