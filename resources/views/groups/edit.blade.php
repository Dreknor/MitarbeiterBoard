@extends('layouts.app')
@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    Gruppe bearbeiten
                </h5>
            </div>
            <div class="card-body">
                <form action="{{url('groups/'.$gruppe->id)}}" method="post" class="form-horizontal">
                    @csrf
                    @method('patch')
                    <div class="form-row">
                        <label for="name">Name der Gruppe</label>
                        <input type="text" class="form-control" name="name" id="name" required autofocus value="{{old('name', $gruppe->name)}}">
                    </div>
                    <div class="form-row mt-1">
                        <div class="col-6">
                            <label for="enddate">aktiv bis</label>
                            <input type="date" class="form-control" name="enddate" id="enddate" value="{{old('enddate', optional($gruppe->enddate)->format('Y-m-d'))}}" @if(!auth()->user()->can('edit groups')) max="{{\Carbon\Carbon::now()->addYear()->format('Y-m-d')}}" required @endif>
                        </div>
                        <div class="col-6">
                            <label for="InvationDays">Tage, die ein Thema vorher angelegt sein muss</label>
                            <input type="number" class="form-control" name="InvationDays" id="InvationDays" value="{{old('InventionDays', $gruppe->InvationDays)}}"  min="1" required>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <label for="homegroup">überführen in</label>
                        <select name="homegroup" class="custom-select">
                            <option disabled></option>
                            @foreach($groups->where('enddate', '') as $newgroup)
                                <option value="{{$newgroup->id}}" @if($gruppe->homegroup == $newgroup->id) selected @endif>{{$newgroup->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row mt-1">
                        <label for="name">Geschützt?</label>
                        <select name="protected" class="custom-select">
                            <option value="1" @if ($gruppe->protected) selected @endif>Gruppe nur für Mitglieder</option>
                            <option value="0" @if (!$gruppe->protected) selected @endif>für alle sichtbar</option>
                        </select>
                    </div>

                    <div class="form-row mt-1">
                        <label for="viewType">Ansicht?</label>
                        <select name="viewType" id="viewType" class="custom-select">
                            <option value="date" @if($gruppe->viewType == 'date') selected @endif>nach Datum</option>
                            <option value="priority" @if(!$gruppe->viewType == 'priority') selected @endif>Priorität</option>
                            <option value="type" @if(!$gruppe->viewType == 'type') selected @endif>Themen-Typ</option>
                        </select>
                    </div>
                    <div class="form-row mt-1">
                        <label for="hasWochenplan">Wochenplan?</label>
                        <select name="hasWochenplan" id="hasWochenplan" class="custom-select">
                            <option value="1" @if($gruppe->hasWochenplan) selected @endif>braucht Wochenplan</option>
                            <option value="0" @if(!$gruppe->hasWochenplan) selected @endif>kein Wochenplan</option>
                        </select>
                    </div>
                    <div class="form-row mt-1">
                        <label for="hasAllocations">ganze Themen einem Benutzer zuweisen?</label>
                        <select name="hasAllocations" id="hasAllocations" class="custom-select">
                            <option value="0" @if(!$gruppe->hasAllocations) selected @endif>nein</option>
                            <option value="1" @if($gruppe->hasAllocations) selected @endif>ja</option>
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

@endsection
