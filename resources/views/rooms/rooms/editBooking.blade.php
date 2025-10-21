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
                    <input type="hidden" name="is_recurring" value="{{$booking->is_recurring ? '1' : '0'}}">

                    <div class="form-row">
                        @if($booking->is_recurring)
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
                                <label>Woche</label>
                                <select name="week" id="week" class="custom-select">
                                    <option value="" @if(old('week', $booking->week) === null) selected @endif> Jede </option>
                                    <option value="A" @if (old('week', $booking->week) == 'A') selected @endif>A-Woche</option>
                                    <option value="B" @if (old('week', $booking->week) == 'B') selected @endif>B-Woche</option>
                                </select>
                            </div>
                        @else
                            <div class="col-sm-3 col-md-4 col-lg-3">
                                <label>Datum</label>
                                <input type="date" name="booking_date" class="form-control p-2" required
                                       value="{{old('booking_date', $booking->booking_date ? $booking->booking_date->format('Y-m-d') : '')}}">
                            </div>
                        @endif

                        <div class="col-sm-3 col-md-4 col-lg-2">
                            <label>Start</label>
                            <input type="time"
                                   name="start" id="start" class="form-control p-2" required
                                   value="{{old('start', $booking->start)}}"
                                   min="{{\Carbon\Carbon::createFromTimeString(config('rooms.start_booking'))->format('H:i')}}"
                                   max="{{\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))->subMinutes(15)->format('H:i')}}"
                                   step="300">
                        </div>
                        <div class="col-sm-3 col-md-4 col-lg-2">
                            <label>Ende</label>
                            <input type="time"
                                   name="end" id="end" class="form-control p-2" required
                                   value="{{old('end', $booking->end)}}"
                                   min="{{\Carbon\Carbon::createFromTimeString(config('rooms.start_booking'))->addMinutes(15)->format('H:i')}}"
                                   max="{{\Carbon\Carbon::createFromTimeString(config('rooms.end_booking'))->format('H:i')}}"
                                   step="300">
                        </div>
                        <div class="col-sm-12 col-md-9 col-lg-{{$booking->is_recurring ? '3' : '4'}}">
                            <label>Bezeichnung</label>
                            <input type="text" maxlength="60" name="name" class="form-control p-2" required value="{{old('name', $booking->name)}}">
                        </div>
                    </div>

                    @if(!$booking->is_recurring)
                        <div class="alert alert-info mt-3">
                            <i class="fa fa-info-circle"></i> Dies ist ein Einzeltermin
                        </div>
                    @endif

                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" class="btn btn-block btn-bg-gradient-x-blue-green">
                                <i class="fa fa-save"></i>
                                Speichern
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer border-danger">
                <h6>Buchung löschen</h6>
                @if($booking->is_recurring)
                    <p class="text-muted">Diese wiederkehrende Buchung wird dauerhaft gelöscht.</p>
                @else
                    <p class="text-muted">Dieser Einzeltermin wird gelöscht.</p>
                @endif
                <form method="post" action="{{url('rooms/booking/'.$booking->id)}}" class="form-horizontal">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn btn-block bg-gradient-radial-danger">Löschen</button>
                </form>
            </div>
        </div>
    </div>
@endsection
