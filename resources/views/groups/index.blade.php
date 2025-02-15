@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
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
                            <div class="form-row mt-1">
                                <label for="meeting_weekday">Wochentag der Besprechungen</label>
                                <select name="meeting_weekday" class="custom-select">
                                    <option  selected></option>
                                    <option value="1">Montag</option>
                                    <option value="2">Dienstag</option>
                                    <option value="3">Mittwoch</option>
                                    <option value="4">Donnerstag</option>
                                    <option value="5">Freitag</option>
                                    <option value="6">Samstag</option>
                                    <option value="7">Sonntag</option>
                                </select>
                            </div>

                            <div class="form-row mt-1">
                                <div class="col-sm-12 col-md-6">
                                    <label for="viewType">Ansicht?</label>
                                    <select name="viewType" id="viewType" class="custom-select">
                                        <option value="date"  selected >nach Datum</option>
                                        <option value="priority" >Priorität</option>
                                        <option value="type" >Themen-Typ</option>
                                    </select>
                                </div>
                                <div class="col-sm-12 col-md-6">
                                    <label for="stack_themes">alte Themen stapeln</label>
                                    <select name="stack_themes" id="stack_themes" class="custom-select">
                                        <option value="1" >Ja</option>
                                        <option value="0" selected>Nein</option>
                                    </select>
                                </div>

                            </div>
                            <div class="form-row mt-1">
                                <label for="hasWochenplan">Wochenplan?</label>
                                <select name="hasWochenplan" id="hasWochenplan" class="custom-select">
                                    <option value="1">braucht Wochenplan</option>
                                    <option value="0" selected>kein Wochenplan</option>
                                </select>
                            </div>
                            <div class="form-row mt-1">
                                <label for="hasAllocations">ganze Themen einem Benutzer zuweisen?</label>
                                <select name="hasAllocations" id="hasAllocations" class="custom-select">
                                    <option value="0" selected >nein</option>
                                    <option value="1" >ja</option>
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

            </div>
        </div>
        <div class="row">

            @foreach($groups as $group)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            @can('edit groups')
                                <div class="d-inline pull-right">
                                    <a href="{{url('groups/'.$group->id.'/edit')}}" class="card-link">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                </div>
                            @endcan
                            <h5 class="card-title">
                                {{$group->name}}
                            </h5>
                            @if($group->enddate != "")
                                <p class="small">
                                    Erstellt von {{$group->creator?->name}} und offen bis {{$group->enddate->format('d.m.Y')}} (noch {{$group->enddate->diffInDays(\Carbon\Carbon::now())}} Tage)
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
                            <div class="card-body" >
                                <button class="btn btn-primary btn-link" type="button" data-toggle="collapse" data-target="#users{{$group->id}}" aria-expanded="false" aria-controls="users{{$group->id}}">
                                    {{$group->users->count()}} Benutzer
                                </button>
                                <div class="collapse" id="users{{$group->id}}">

                                <ul class="list-group " >
                                    @if($group->users->count() == 0)
                                        <li class="list-group-item"> Keine Benutzer in der Gruppe </li>
                                    @else
                                        @foreach($group->users as $user)
                                            <li class="list-group-item">
                                                {{$user->name}}
                                                @if($group->creator_id != "" and $group->creator_id == auth()->id() or auth()->user()->can('edit groups'))
                                                    <div class="d-inline pull-right">
                                                        <form action="{{url($group->name.'/removeUser')}}" method="post" class="form-inline">
                                                            @csrf
                                                            @method('delete')
                                                            <input type="hidden" name="user_id" value="{{$user->id}}" >
                                                            <button type="submit" class="btn btn-danger btn-link d-inline"><i class="fas fa-user-minus"></i></button>
                                                        </form>
                                                    </div>

                                                @endif
                                            </li>
                                        @endforeach
                                    @endif
                                </ul>
                                </div>
                            </div>
                        @else
                            <div class="card-body bg-info">
                                Die Gruppe ist offen für alle
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
