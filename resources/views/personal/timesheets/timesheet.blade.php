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
                    <a href="{{url('timesheets/'.$employe->id.'/'.$month->copy()->subMonth()->format('Y-m'))}}" class="btn btn-sm btn-outline-primary"> <-- </a>
                    </div>
                    <div class="d-inline">
                    {{$month->monthName}} {{$month->year}} @if($timesheet->is_locked) <i class="fa-solid fa-lock"></i> @endif
                    </div>
                    <div class="d-inline">
                        <a href="{{url('timesheets/'.$employe->id.'/'.$month->copy()->addMonth()->format('Y-m'))}}" class="btn btn-sm btn-outline-primary"> --> </a>
                    </div>
                    <div class="d-inline pull-right">
                        <div class="dropleft">
                            <button class="btn btn-sm btn-bg-gradient-x-blue-green dropdown-toggle"  data-toggle="dropdown"  id="dropdownMenuButton_selectMonth">
                                <i class="fa fa-scroll"></i> {{$month->monthName}} {{$month->year}}
                            </button>
                            <ul class="dropdown-menu pre-scrollable">
                                @for($selectMonth = \Carbon\Carbon::now(); $selectMonth->greaterThanOrEqualTo($employe->employments->sortBy('start')->first()->start); $selectMonth->subMonth())
                                    <li>
                                        <a href="{{url('timesheets/'.$employe->id.'/'.$selectMonth->format('Y-m'))}}" class="dropdown-item text-info">
                                            {{$selectMonth->monthName}} {{$selectMonth->year}}
                                        </a>
                                    </li>

                                @endfor
                            </ul>
                        </div>
                    </>
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
                                <div class="row">
                                    <div class="col-auto">
                                        Stundenkonto: {{convertTime($timesheet_old->working_time_account)}} h
                                    </div>
                                    <div class="col-auto">
                                        Urlaub bisher: {{$timesheet_old->holidays_old}}
                                    </div>
                                    <div class="col-auto">
                                        Urlaub Rest: {{$timesheet_old->holidays_rest}}
                                    </div>
                                </div>
                            @else
                                kein Arbeitszeitnachweis gespeichert
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
                            @if(!is_null($timesheet))
                                <div class="row">
                                    <div class="col-auto">
                                        Stundenkonto: {{convertTime($timesheet?->working_time_account)}} h ({{convertTime($timesheet?->working_time_account - $timesheet_old?->working_time_account)}} h)
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
                                @if(!$timesheet->is_locked)
                                    <div class="row">
                                        <div class="col-12">
                                            <a href="{{url('timesheets/'.$employe->id.'/'.$timesheet->id.'/lock')}}" class=" btn btn-sm btn-block btn-bg-gradient-x-orange-yellow">
                                                abschließen
                                            </a>
                                        </div>
                                    </div>
                                @else
                                <div class="row">
                                    <div class="col-12">
                                        <a href="{{url('timesheets/'.$employe->id.'/export/'.$timesheet->id)}}" class=" btn btn-sm btn-block btn-bg-gradient-x-orange-yellow">
                                            EXPORT
                                        </a>
                                    </div>
                                </div>
                                @endif

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
                        @if(!$timesheet->is_locked)
                        <th>

                        </th>
                        @endif
                    </tr>
                    @for($day = $month->copy()->startOfMonth(); $day->lessThanOrEqualTo($month->copy()->endOfMonth()); $day->addDay())
                        @include('personal.timesheets.day')
                    @endfor
                </table>
            </div>
        </div>
    </div>
@endsection
