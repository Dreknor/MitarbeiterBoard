@extends('layouts.app')

@section('title')
    Arbeitszeitnachweis
@endsection

@section('site-title')
    Arbeitszeitnachweis
@endsection

@section('content')
    <a href="{{url('timesheets/'.$user->id.'/'.$timesheet->year.'-'.$timesheet->month)}}" class="btn btn-primary btn-link" >zurück</a>
    <div class="card">
        <div class="card-header">
            <h6>
                neue Arbeitszeit:
            </h6>
            <p>
                {{$day->dayName}}, {{$day->format('d.m.Y')}}
            </p>
        </div>
        <div class="card-body">
            <div class="container-fluid">
                <form action="{{url('timesheets/'.$user->id.'/'.$timesheet->id.'/'.$day->format('Y-m-d').'/store')}}" method="post" class="form-horizontal w-100">
                    @csrf
                    <div class="row">
                        <label class="label w-100">
                            Anfangszeit:
                            <input type="time" class="form-control" name="start" required>
                        </label>
                    </div>
                    <div class="row">
                        <label class="label w-100">
                            Endzeitzeit:
                            <input type="time" class="form-control" name="end" required>
                        </label>
                    </div>
                    <div class="row">
                        <label class="label w-100">
                            Pause:
                            <input type="number" min="0" class="form-control" name="pause">
                        </label>
                    </div>
                    <div class="row">
                        <label class="label w-100">
                            Anmerkung:
                            <input type="text" max="60" class="form-control" name="comment">
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
