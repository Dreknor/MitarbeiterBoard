@extends('layouts.app')
@section('content')
    <div class="container-fluid">
        <a href="{{url(request()->segment(1)."/wochenplan")}}" class="btn btn-primary">zurück</a>
        <div class="card">
            <div class="card-header border-bottom bg-light">
                <h6>{{$wochenplan->name}}</h6>
                <p class="small">
                    gültig von {{$wochenplan->gueltig_ab->format('d.m.Y')}} bis {{$wochenplan->gueltig_bis->format('d.m.Y')}}
                </p>
                <p class="small">
                    @foreach($wochenplan->klassen as $klasse)
                        {{$klasse->name}}@if(!$loop->last),@endif
                    @endforeach
                </p>
            </div>
            @foreach($wochenplan->rows as $row)
                <div class="card-body border-bottom">
                    <div class="row p-3">
                        <div class="col-4 border-right">
                            <b>
                                {{$row->name}}
                            </b>
                        </div>
                        <div class="col-7">
                            Aufgaben
                        </div>
                        <div class="col-1">
                            <a href="#" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="card-footer bg-light">
                <b>Neuer Abschnitt</b>
                <form action="{{url('wprow/'.$wochenplan->id)}}" class="form-inline" method="post">
                    @csrf
                    <input type="hidden" name="wochenplan_id" value="{{$wochenplan->id}}">
                    <div class="form-check w-75 m-2">
                        <label class="sr-only" for="name">Name</label>
                        <input type="text" class="form-control w-100 p-2 pull-left" id="name" name="name" placeholder="Bezeichnung/Fach">
                    </div>

                    <button type="submit" class="btn btn-primary">Abschnitt erstellen</button>
                </form>
            </div>
        </div>
    </div>


@endsection
