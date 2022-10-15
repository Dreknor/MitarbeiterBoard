@extends('layouts.app')

@section('content')
    <a href="{{url('rooms/rooms/'.$room->id)}}" class="btn btn-primary btn-link" >zurück</a>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <div class="d-flex w-100 justify-content-between">
                    <h5>
                        bearbeite Reservierung für {{$room->name}} ({{$room->room_number}})
                    </h5>

                </div>
            </div>
            <div class="card-body" id="createForm">
                <form method="post" action="{{url('rooms/bookings/'.$booking->id)}}" class="form-horizontal">
                    @csrf
                    @method('put')
                    <input type="hidden" name="room_id" value="{{$room->id}}">

                    <div class="form-row">
                        <div class="col-sm-3 col-md-4 col-lg-3">
                            <label>Wochentag</label>
                            <select name="weekday" id="weekday" class="custom-select" required>
                                <option disabled selected></option>
                                @foreach(config('config.days') as $key => $day)
                                    <option value="{{$key}}"  @if (old('weekday', $booking->weekday) == $key) selected @endif>{{$day}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3 col-md-4 col-lg-2">
                            <label>Start</label>
                            <select name="start" id="start" class="custom-select" required>
                                <option disabled selected></option>
                                @for($time = \Carbon\Carbon::createFromTimeString(config('rooms.start_booking')); $time->lessThanOrEqualTo(\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))); $time->addMinutes(15))
                                    <option value="{{$time->format('H:i')}}"  @if (old('start', $booking->start) == $time->format('H:i:00')) selected @endif>{{$time->format('H:i')}} Uhr</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-sm-3 col-md-4 col-lg-2">
                            <label>Ende</label>
                            <select name="end" id="end" class="custom-select" required>
                                <option disabled selected></option>
                                @for($time = \Carbon\Carbon::createFromTimeString(config('rooms.start_booking')); $time->lessThanOrEqualTo(\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))); $time->addMinutes(15))
                                    <option value="{{$time->format('H:i')}}"  @if (old('end', $booking->end) == $time->format('H:i:00')) selected @endif>{{$time->format('H:i')}} Uhr</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-sm-12 col-md-9 col-lg-4">
                            <label>Bezeichnung</label>
                            <input type="text" maxlength="60" name="name" class="form-control p-2" required value="{{$booking->name}}">
                        </div>

                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="mt-4 btn btn-block btn-bg-gradient-x-blue-green">
                                <i class="fa fa-save"></i>
                                Speichern
                            </button>
                        </div>
                    </div>

                </form>
            </div>
            <div class="card-footer border-danger">
                <h6>Buchung löschen</h6>
                <form method="post" action="{{url('rooms/booking/'.$booking->id)}}" class="form-horizontal">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn btn-block bg-gradient-radial-danger">Löschen</button>
                </form>
            </div>
        </div>
    </div>

@endsection
