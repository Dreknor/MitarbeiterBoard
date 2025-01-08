@extends('layouts.app')
@section('content')
    <a href="{{url('rooms/rooms')}}" class="btn btn-primary btn-link" >zurück</a>

    <div class="card-footer border-top">
            <form action="{{url('rooms/rooms/'.$room->id)}}" method="post" class="form-horizontal">
                @csrf
                @method('put')
                <div class="form-row mb-2">
                    <label class="label w-100">
                        Name
                        <input class="form-control" type="text" name="name" value="{{$room->name}}" required>
                    </label>
                </div>
                <div class="form-row mb-2">
                    <label class="label w-100">
                        Raumnummer
                        <input class="form-control" type="text" name="room_number" value="{{$room->room_number}}">
                    </label>
                </div>
                <div class="form-row mb-2">
                    <label class="label w-100">
                        Indiware Kürzel
                        <input class="form-control" type="text" name="indiware_shortname" value="{{$room->indiware_shortname}}" maxlength="10">
                    </label>
                </div>
                <div class="form-row">
                    <button class="btn btn-success btn-bg-gradient-x-blue-green btn-block" type="submit">
                        Raum speichern
                    </button>
                </div>
            </form>
        </div>
@endsection
