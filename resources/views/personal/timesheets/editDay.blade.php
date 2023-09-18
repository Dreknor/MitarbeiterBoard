@extends('layouts.app')

@section('title')
    Arbeitszeitnachweis
@endsection

@section('site-title')
    Arbeitszeitnachweis
@endsection

@section('content')
    <a href="{{url()->previous() }}" class="btn btn-primary btn-link" >zurück</a>
    <div class="card">
        <div class="card-header">
            <h6>
                Arbeitszeit bearbeiten:
            </h6>
            <p>
                {{$day->dayName}}, {{$day->format('d.m.Y')}}
            </p>
        </div>
        <div class="card-body">
            <div class="container-fluid">
                <form action="{{url('timesheets/day/'.$timesheet_day->id.'/edit')}}" method="post" class="form-horizontal w-100">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <label class="label w-100">
                            Anfangszeit:
                            <input type="time" class="form-control" name="start" required value="{{$timesheet_day->start->format('H:i')}}">
                        </label>
                    </div>
                    <div class="row">
                        <label class="label w-100">
                            Endzeitzeit:
                            <input type="time" class="form-control" name="end" required  value="{{$timesheet_day->end->format('H:i')}}">
                        </label>
                    </div>
                    <div class="row">
                        <label class="label w-100">
                            Pause:
                            <input type="number" min="0" class="form-control" name="pause"  @if(!is_null($timesheet_day->pause) and $timesheet_day->pause != 0) value="{{$timesheet_day->pause}}" @endif>
                        </label>
                    </div>
                    <div class="row">
                        <label class="label w-100">
                            Anmerkung:
                            <input type="text" max="60" class="form-control" name="comment"  value="{{$timesheet_day->comment}}">
                        </label>
                    </div>
                    <div class="row">
                        <button type="submit" class="btn btn-bg-gradient-x-blue-green">speichern</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
@endsection
