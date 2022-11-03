@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5>
                    vorhandene Räume
                </h5>
            </div>
            <div class="card-body">
                @if($rooms->count() > 0)
                    <table class="table table-striped w-100">
                    <thead>
                        <tr>
                            <th>
                                Name
                            </th>
                            <th>
                                Raum-Nummer
                            </th>
                            <th>
                                Aktionen
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rooms as $room)
                            <tr>
                                <td>
                                    {{$room->name}}
                                </td>
                                <td>
                                    {{$room->room_number}}
                                </td>
                                <td>
                                    <a href="{{url('rooms/rooms/'.$room->id.'')}}" class="btn btn-bg-gradient-x-blue-purple-1 btn-sm">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @can('manage rooms')
                                        <a href="{{url('rooms/rooms/'.$room->id.'/edit')}}" class="btn btn-sm btn-bg-gradient-x-orange-yellow">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endcan
                                    <!--
                                    <a href="{{url('rooms/rooms/'.$room->id.'/export')}}" class="btn btn-bg-gradient-x-blue-green btn-sm">
                                        <i class="fa fa-file-export"></i>
                                    </a>
                                    -->

                                    @if($room->bookings->count() == 0)
                                        <button class="btn btn-sm btn-bg-gradient-x-red-pink" type="submit" title="Raum löschen" form="deleteForm_{{$room->id}}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                            <form method="post" id="deleteForm_{{$room->id}}" action="{{url('rooms/rooms/'.$room->id)}}" class="form-inline m-1">
                                                @csrf
                                                @method('delete')
                                            </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <p>Es wurden noch keine Räume angelegt</p>
                @endif
            </div>
            @can('manage rooms')
                <div class="card-footer border-top">
                <form action="{{url('rooms/rooms')}}" method="post" class="form-horizontal">
                    @csrf
                    <div class="form-row mb-2">
                        <label class="label w-100">
                            Name
                            <input class="form-control" type="text" name="name" required>
                        </label>
                    </div>
                    <div class="form-row mb-2">
                        <label class="label w-100">
                            Raumnummer
                            <input class="form-control" type="text" name="room_number">
                        </label>
                    </div>
                    <div class="form-row">
                            <button class="btn btn-success btn-bg-gradient-x-blue-green btn-block" type="submit">
                                Raum erstellen
                            </button>
                    </div>
                </form>
            </div>
            @endcan
        </div>
    </div>
@endsection
