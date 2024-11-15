@extends('layouts.app')

@section('content')
    <a href="{{url('rooms/rooms')}}" class="btn btn-primary btn-link" >zurück</a>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <div class="d-flex w-100 justify-content-between">
                    <h5>
                        {{$room->name}} ({{$room->room_number}})
                    </h5>
                    <a class="btn btn-bg-gradient-x-blue-green" data-toggle="collapse" href="#createForm" role="button">
                        neue Reservierung
                    </a>
                </div>
            </div>
            <div class="card-body collapse" id="createForm">
                <form method="post" action="{{url('rooms/bookings/')}}" class="form-horizontal">
                    @csrf
                    <input type="hidden" name="room_id" value="{{$room->id}}">
                    <div class="form-row">
                        <div class="col-sm-3 col-md-3 col-lg-2">
                            <label>Wochentag</label>
                            <select name="weekday" id="weekday" class="custom-select" required>
                                <option disabled selected></option>
                                @foreach(config('config.days') as $key => $day)
                                    <option value="{{$key}}"  @if (old('weekday') == $key) selected @endif>{{$day}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3 col-md-3 col-lg-2">
                            <label>Start</label>
                            <input type="time" name="start" class="form-control p-2" required min="{{config('booking.start_time')}}" max="{{config('booking.end_time')}}" step="300">
                        </div>
                        <div class="col-sm-3 col-md-3 col-lg-2">
                            <label>Ende</label>
                            <input type="time" name="end" class="form-control p-2" required min="{{config('booking.start_time')}}" max="{{config('booking.end_time')}}" step="300">
                        </div>
                        <div class="col-sm-3 col-md-2 col-lg-2">
                            <label>Woche</label>
                            <select name="week" id="week" class="custom-select" required>
                                <option selected> Jede </option>
                                <option value="A" @if (old('week') == 'A') selected @endif>A-Woche</option>
                                <option value="B" @if (old('week') == 'B') selected @endif>B-Woche</option>
                            </select>
                        </div>

                        <div class="col-sm-12 col-md-8 col-lg-3">
                            <label>Bezeichnung</label>
                            <input type="text" maxlength="60" name="name" class="form-control p-2" required>
                        </div>
                        <div class="col-sm-12 col-md-2 col-lg-1">
                            <button type="submit" class="mt-4 btn btn-block btn-bg-gradient-x-blue-green">
                                <i class="fa fa-save"></i>
                            </button>
                        </div>
                    </div>

                </form>
            </div>

            <div class="card-body">
                @if($room->bookings()->where('week', '!=', null)->count() > 0)
                    <div class="row">
                        <div class="col-6">
                            <a href="{{url('rooms/rooms/'.$room->id.'/A')}}" class="btn btn-block @if($week == 'A') disabled @endif btn-bg-gradient-x-blue-green">
                               A-Woche
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{url('rooms/rooms/'.$room->id.'/B')}}" class="btn btn-block btn-bg-gradient-x-blue-green  @if($week == 'B') disabled @endif ">
                                B-Woche
                            </a>
                        </div>
                    </div>
                @endif
                <h6>Buchungen  @if($room->bookings()->where('week', '!=', null)->count() > 0) {{$week}}-Woche @endif</h6>
                <div class="table-responsive-sm">
                    <table class="table table-striped table-sm ">
                        <thead>
                        <tr>
                            <th></th>
                            <th class="border-right">
                                Montag
                            </th>
                            <th class="border-right">
                                Dienstag
                            </th>
                            <th class="border-right">
                                Mittwoch
                            </th>
                            <th class="border-right">
                                Donnerstag
                            </th>
                            <th class="border-right">
                                Freitag
                            </th>
                            <th class="border-right">
                                Samstag
                            </th>
                            <th class="border-right">
                                Sonntag
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        @for($time = \Carbon\Carbon::createFromTimeString(config('rooms.start_booking')); $time->lessThanOrEqualTo(\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))); $time->addMinutes(5))
                            <tr>
                                <td class="border-right border-bottom pt-0 pb-0" >
                                        <small>
                                            {{$time->format('H:i')}}
                                        </small>
                                </td>
                                @for($day = \Carbon\Carbon::now()->startOfWeek(); $day->lessThanOrEqualTo(\Carbon\Carbon::now()->endOfWeek()); $day->addDay())
                                    @if($room->hasBooking($day->dayOfWeek, $time->format('H:i'), $week))
                                        @if($room->hasBooking($day->dayOfWeek, $time->format('H:i'), $week)->start == $time->format('H:i:00'))
                                            <td class="text-white text-center @if( \Carbon\Carbon::now()->gt(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' '.$time->format('H:i')))) bg-gradient-x-light-blue @else bg-gradient-radial-blue  @endif"

                                                rowspan="{{$room->hasBooking($day->dayOfWeek, $time->format('H:i'), $week)->duration / 5}}">
                                                {{$room->hasBooking($day->dayOfWeek, $time->format('H:i'), $week)->name}}
                                            </td>
                                        @else
                                            @continue
                                        @endif

                                    @else
                                        <td class="border-right border-bottom pt-0 pb-0"  @if( \Carbon\Carbon::now()->gt(\Carbon\Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d').' '.$time->format('H:i')))) style="background-color: rgba(197,191,191,0.34)" @else  @endif>

                                        </td>
                                    @endif

                                @endfor

                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
@endsection


