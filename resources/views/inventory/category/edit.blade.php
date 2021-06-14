@extends('layouts.app')

@section('content')
<a href="{{url('inventory/locations')}}" class="btn btn-primary">zurück</a>
    <div class="card">
        <div class="card-header">
            <h5>
                {{$category->name}} bearbeiten
            </h5>
        </div>
        <div class="card-body">
            <form action="{{url('inventory/categories/'.$categoriy->id)}}" method="post" class="form-horizontal">
                @csrf
                @method('put')
                <div class="form-row">
                    <label for="name">Bezeichnung</label>
                    <input type="text" name="name"  id="name" class="form-control" value="{{old('name', $location->name)}}">
                </div>
                <div class="form-row">
                    <label for="type">Typ</label>
                    <select name="type" id="type" class="custom-select">
                        <option></option>
                        @foreach($categories as $Category)
                            <option value="{{$Category->id}}" @if($category->parent->id  == $Category->id) selected @endif>
                                {{$Category->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-success btn-block">speichern</button>
            </form>
        </div>
    </div>
@endsection
