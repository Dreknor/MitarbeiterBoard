@extends('layouts.app')

@section('content')
<a href="{{url('inventory/lieferanten')}}" class="btn btn-primary">zurück</a>
    <div class="card">
        <div class="card-header">
            <h5>
                neuen Lieferanten anlegen
            </h5>
        </div>
        <div class="card-body">
            <form action="{{url('inventory/lieferanten')}}" method="post" class="form-horizontal">
                @csrf
                <div class="form-row">
                    <label for="name">Name  <i class="text-danger">(benötigt)</i></label>
                    <input type="text" name="name"  id="name" class="form-control" value="{{old('name')}}" autofocus>
                </div>
                <div class="form-row">
                    <label for="kuerzel">Kürzel  <i class="text-danger">(benötigt)</i></label>
                    <input type="text" name="kuerzel"  id="kuerzel" class="form-control" value="{{old('kuerzel')}}">
                </div>
                <button type="submit" class="btn btn-success btn-block">speichern</button>
            </form>
        </div>
    </div>
@endsection
