@extends('layouts.app')


@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <a href="{{url($group->name.'/themes#'.$date->format('Ymd'))}}" class="btn btn-prev">
                    zurück zu den Themen
                </a>
            </div>
        </div>
        <div class="sticky-top">
            <div class="card ">
                <div class="card-header">
                    <h5>
                        Anwesenheit zur Besprechung am {{$date->format('d.m.Y')}}
                </div>
                @if($date->isToday())
                    <div class="card-body">
                    <form method="post" action="{{url($group->name.'/presences/add')}}">
                        @csrf

                    <ul class="list-group">
                        @foreach($users as $user)
                            <li class="list-group-item">
                                <div class="row p-1 mb-1">
                                    <div class="col-6 ">
                                        @if($user->getMedia('profile')->count() != 0)
                                            <img src="{{$user->photo()}}" class="avatar-xs" title="{{$user->name}}">
                                        @endif
                                        {{$user->name}}

                                    </div>
                                    <div class="col-auto">
                                        <div class="container-fluid">
                                            <div class="row">
                                                <div class="col-auto">
                                                    <label for="presence_{{$user->id}}">Anwesend
                                                        <input type="radio"
                                                               name="presence_{{$user->id}}"
                                                               value="presence"
                                                               class="custom-control"
                                                                @if($presences->where('user_id', $user->id)->where('presence', '1')->count() > 0)
                                                                    checked
                                                                @endif
                                                        >
                                                    </label>
                                                </div>
                                                <div class="col-auto">
                                                    <label for="presence_{{$user->id}}">online
                                                        <input type="radio"
                                                               name="presence_{{$user->id}}"
                                                               value="online"
                                                               class="custom-control"
                                                               @if($presences->where('user_id', $user->id)->where('online', '1')->count() > 0)
                                                                   checked
                                                            @endif
                                                        >
                                                    </label>
                                                </div>
                                                <div class="col-auto">
                                                    <label for="presence_{{$user->id}}">entschuldigt
                                                        <input type="radio"
                                                               name="presence_{{$user->id}}"
                                                               value="excused"
                                                               class="custom-control"
                                                               @if($presences->where('user_id', $user->id)->where('excused', '1')->count() > 0)
                                                                   checked
                                                               @endif
                                                        >
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                        <button type="submit" class="btn btn-primary btn-block">
                            speichern
                        </button>
                    </form>
                </div>
                @else
                    <div class="card-body">

                            <ul class="list-group">
                                @foreach($users as $user)
                                    <li class="list-group-item">
                                        <div class="row p-1 mb-1">
                                            <div class="col-6 ">
                                                @if($user->getMedia('profile')->count() != 0)
                                                    <img src="{{$user->photo()}}" class="avatar-xs" title="{{$user->name}}">
                                                @endif
                                                {{$user->name}}
                                            </div>
                                            <div class="col-auto">
                                                <div class="container-fluid">
                                                    <div class="row">
                                                        <div class="col-auto">
                                                            @if($presences->where('user_id', $user->id)->where('presence', '1')->count() > 0)
                                                                <i class="fas fa-check text-success"></i> anwesend
                                                            @endif
                                                        </div>
                                                        <div class="col-auto">
                                                            @if($presences->where('user_id', $user->id)->where('online', '1')->count() > 0)
                                                                <i class="fas fa-wifi text-info"></i> online
                                                            @endif
                                                        </div>
                                                        <div class="col-auto">
                                                            @if($presences->where('user_id', $user->id)->where('excused', '1')->count() > 0)
                                                                <i class="fas fa-ban text-danger"></i> entschuldigt
                                                            @endif
                                                        </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                    </div>

                @endif
                <div class="card-footer border-top">
                    <h5>
                        Gäste
                    </h5>
                    <ul class="list-group
                    ">
                        @foreach($presences->filter(function ($presence){
                            return $presence->guest_name != null;
}                       ) as $guest)
                            <li class="list-group-item">
                                {{$guest->guest_name}}
                                @if($date->isToday())
                                    <a href="{{url($group->name.'/presences/'.$guest->id.'/deleteGuest')}}" class="btn btn-danger btn-sm float-right">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if($date->isToday())
                        <form method="post" action="{{url($group->name.'/presences/addGuest')}}" class="form-horizontal mt-3">
                            @csrf
                            <input type="text" name="guest_name" class="form-control mb-2" placeholder="Name des Gastes">
                            <button type="submit" class="btn btn-info btn-block">
                                Gast hinzufügen
                            </button>
                        </form>
                    @endif

                </div>
            </div>
        </div>
    </div>
@endsection
