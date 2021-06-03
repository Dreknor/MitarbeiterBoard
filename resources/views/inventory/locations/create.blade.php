@extends('layouts.app')

@section('content')
<a href="{{url('inventory/locations')}}" class="btn btn-primary">zurück</a>
    <div class="card">
        <div class="card-header">
            <h5>
                neuen Ort anlegen
            </h5>
        </div>
        <div class="card-body">
            <form action="{{url('inventory/locations')}}" method="post" class="form-horizontal">
                @csrf
                <div class="form-row">
                    <label for="kennzeichnung">Raumkennzeichnung</label>
                    <input type="text" name="kennzeichnung"  id="kennzeichnung" class="form-control" value="{{old('kennzeichnung')}}">
                </div>
                <div class="form-row">
                    <label for="name">
                        Name <i class="text-danger">(benötigt)</i>
                    </label>
                    <input type="text" name="name" id="name" class="form-control" value="{{old('name')}}">
                </div>
                <div class="form-row">
                    <label for="description">Beschreibung</label>
                    <input type="text" name="description" id="description" class="form-control" value="{{old('description')}}">
                </div>
                <div class="form-row">
                    <label for="type">Typ</label>
                    <select name="type" id="type" class="custom-select">
                        <option></option>
                        @foreach($types as $type)
                            <option value="{{$type->id}}">
                                {{$type->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="user">Verantwortlicher</label>
                    <select name="user" id="user" class="custom-select">
                        <option></option>
                        @foreach($users as $user)
                            <option value="{{$user->id}}">
                                {{$user->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-success btn-block">speichern</button>
            </form>
        </div>
    </div>
@endsection
