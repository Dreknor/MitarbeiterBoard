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
                        <div class="col-sm-3 col-md-4 col-lg-3">
                            <label>Wochentag</label>
                            <select name="weekday" id="weekday" class="custom-select" required>
                                <option disabled selected></option>
                                @foreach(config('config.days') as $key => $day)
                                    <option value="{{$key}}"  @if (old('weekday') == $key) selected @endif>{{$day}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3 col-md-4 col-lg-2">
                            <label>Start</label>
                            <select name="start" id="start" class="custom-select" required>
                                <option disabled selected></option>
                                @for($time = \Carbon\Carbon::createFromTimeString(config('rooms.start_booking')); $time->lessThanOrEqualTo(\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))); $time->addMinutes(15))
                                    <option value="{{$time->format('H:i')}}"  @if (old('start') == $time->format('H:i')) selected @endif>{{$time->format('H:i')}} Uhr</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-sm-3 col-md-4 col-lg-2">
                            <label>Ende</label>
                            <select name="end" id="end" class="custom-select" required>
                                <option disabled selected></option>
                                @for($time = \Carbon\Carbon::createFromTimeString(config('rooms.start_booking')); $time->lessThanOrEqualTo(\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))); $time->addMinutes(15))
                                    <option value="{{$time->format('H:i')}}"  @if (old('end') == $time->format('H:i')) selected @endif>{{$time->format('H:i')}} Uhr</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-sm-12 col-md-9 col-lg-4">
                            <label>Bezeichnung</label>
                            <input type="text" maxlength="60" name="name" class="form-control p-2" required>
                        </div>
                        <div class="col-sm-12 col-md-3 col-lg-1">
                            <button type="submit" class="mt-4 btn btn-block btn-bg-gradient-x-blue-green">
                                <i class="fa fa-save"></i>
                            </button>
                        </div>
                    </div>

                </form>
            </div>
            <div class="card-body">
                <h6>Buchungen</h6>
                <table class="table table-striped">
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
                    </tr>
                    </thead>
                    <tbody>
                        @for($time = \Carbon\Carbon::createFromTimeString(config('rooms.start_booking')); $time->lessThanOrEqualTo(\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))); $time->addMinutes(15))
                            <tr>
                                <td class="border-right border-bottom">
                                    <small>
                                        {{$time->format('H:i')}}
                                    </small>
                                </td>
                                @for($x = 1; $x<6; $x++)
                                    @if($room->hasBooking($x, $time->format('H:i')) and $room->hasBooking($x, $time->format('H:i'))->start == $time->format('H:i:00'))
                                            <td class="border-right border-bottom text-white text-center bg-gradient-directional-info"
                                                rowspan="{{ceil($room->hasBooking($x, $time->format('H:i'))->duration/15)+1}}"
                                                onclick="location.href='{{url('rooms/booking/'.$room->hasBooking($x, $time->format('H:i'))->id)}}'">
                                                {{$room->hasBooking($x, $time->format('H:i'))->name}}
                                            </td>
                                    @elseif($room->hasBooking($x, $time->format('H:i')) == null)
                                        <td class="border-right border-bottom">

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
@endsection
