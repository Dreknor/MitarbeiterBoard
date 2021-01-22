@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card-deck">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        neue Gruppe anlegen
                    </h5>
                    <p class="small">neue Gruppen sind auf maximal 1 Jahr befristet und werden dann in eine der Hauptgruppen überführt</p>
                </div>
                <div class="card-body">
                    <form action="{{url('groups')}}" method="post" class="form-horizontal">
                        @csrf
                        <div class="form-row">
                            <label for="name">Name der neuen Gruppe</label>
                            <input type="text" class="form-control" name="name" id="name" required autofocus>
                        </div>
                        <div class="form-row mt-1">
                            <label for="enddate">aktiv bis</label>
                            <input type="date" class="form-control" name="enddate" id="enddate" @if(!auth()->user()->can('edit groups')) max="{{\Carbon\Carbon::now()->addYear()->format('Y-m-d')}}" required @endif>
                        </div>
                        <div class="form-row mt-1">
                            <label for="name">überführen in</label>
                            <select name="homegroup" class="custom-select">
                                    <option disabled selected></option>
                                @foreach($groups->where('enddate', '') as $newgroup)
                                    <option value="{{$newgroup->id}}">{{$newgroup->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row mt-1">
                            <label for="name">Geschützt?</label>
                            <select name="protected" class="custom-select">
                                    <option disabled selected></option>
                                    <option value="1">Gruppe nur für Mitglieder</option>
                                    <option value="0">für alle sichtbar</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <button type="submit" class="btn btn-success btn-block">
                                speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @foreach($groups as $group)
                @if($loop->index%2 == 0)
                    <div class="w-100 d-none d-sm-block d-md-none"><!-- wrap every 2 on sm--></div>
                @elseif($loop->index%3 == 0)
                    <div class="w-100 d-none d-md-block d-lg-none"><!-- wrap every 3 on md--></div>
                @elseif($loop->index%5 == 0)
                    <div class="w-100 d-none d-lg-block "><!-- wrap every 3 on md--></div>
                @endif
                <div class="card m-1">
                    <div class="card-header">
                        <h5 class="card-title">
                            {{$group->name}}
                        </h5>
                        @if($group->enddate != "")
                            <p class="small">
                                Erstellt von {{$group->creator->name}} und offen bis {{$group->enddate->format('d.m.Y')}} (noch {{$group->enddate->diffInDays(\Carbon\Carbon::now())}} Tage)
                            </p>
                        @endif
                        <p class="small">
                            Themen müssen <b>{{$group->InvationDays}}</b> Tage vorher angelegt werden
                        </p>
                    </div>
                    @if($group->protected)
                        <div class="card-footer">
                            @if($group->creator_id != "" and $group->creator_id == auth()->id() or auth()->user()->can('edit groups'))
                                <form action="{{url($group->name.'/addUser')}}" method="post">
                                    @csrf
                                    @method('put')
                                    <div class="form-row">
                                        <label for="name">Mitarbeiter hinzufügen</label>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" name="name" id="name" required>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-primary" type="submit">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>

                                    </div>

                                </form>
                            @endif
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                @if($group->users->count() == 0)
                                    <li class="list-group-item"> Keine Benutzer in der Gruppe </li>
                                @else
                                    @foreach($group->users as $user)
                                        <li class="list-group-item">
                                            {{$user->name}}
                                            @if($group->creator_id != "" and $group->creator_id == auth()->id() or auth()->user()->can('edit groups'))
                                                <div class="pull-right">
                                                    <form action="{{url($group->name.'/removeUser')}}" method="post">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" name="user_id" value="{{$user->id}}" class="btn btn-danger btn-xs"><i class="fas fa-user-minus"></i></button>
                                                    </form>
                                                </div>

                                            @endif
                                        </li>
                                    @endforeach
                                @endif
                            </ul>
                        </div>
                    @else
                        <div class="card-body bg-info">
                            Die Gruppe ist offen für alle
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection
