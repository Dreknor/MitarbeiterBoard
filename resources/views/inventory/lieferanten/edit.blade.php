@extends('layouts.app')

@section('content')
<a href="{{url('inventory/locations')}}" class="btn btn-primary">zurück</a>
    <div class="card">
        <div class="card-header">
            <h5>
                {{$lieferant->name}} bearbeiten
            </h5>
        </div>
        <div class="card-body">
            <form action="{{url('inventory/lieferanten/'.$lieferant->id)}}" method="post" class="form-horizontal">
                @csrf
                @method('put')
                <div class="form-row">
                    <label for="name">
                        Name  <i class="text-danger">(benötigt)</i>
                    </label>
                    <input type="text" name="name"  id="name" class="form-control" value="{{old('name', $lieferant->name)}}">
                </div>
                <div class="form-row">
                    <label for="kurzel">
                        Kürzel  <i class="text-danger">(benötigt)</i>
                    </label>
                    <input type="text" name="kuerzel"  id="kuerzel" class="form-control" value="{{old('name', $lieferant->kuerzel)}}">
                </div>
                <button type="submit" class="btn btn-success btn-block">speichern</button>
            </form>
        </div>
    </div>
@endsection
