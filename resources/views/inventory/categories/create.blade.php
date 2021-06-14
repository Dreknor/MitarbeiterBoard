@extends('layouts.app')

@section('content')
<a href="{{url('inventory/locations')}}" class="btn btn-primary">zurück</a>
    <div class="card">
        <div class="card-header">
            <h5>
                neue Kategorie anlegen
            </h5>
        </div>
        <div class="card-body">
            <form action="{{url('inventory/categories')}}" method="post" class="form-horizontal">
                @csrf
                <div class="form-row">
                    <label for="name">Bezeichnung</label>
                    <input type="text" name="name"  id="name" class="form-control" value="{{old('name')}}" autofocus>
                </div>
                <div class="form-row">
                    <label for="parent_id">Unterkategorie von ...</label>
                    <select name="parent_id" id="parent_id" class="custom-select">
                        <option></option>
                        @foreach($categories as $categotry)
                            <option value="{{$categotry->id}}">
                                {{$categotry->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-success btn-block">speichern</button>
            </form>
        </div>
    </div>
@endsection
