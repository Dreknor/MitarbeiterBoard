@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            Klasse bearbeiten
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{url('klassen/'.$klasse->id)}}" method="post" class="form-horizontal">
                            @csrf
                            @method("put")
                            <div class="form-row">
                                <div class="col-md-4 col-sm-12">
                                    <label for="name">
                                        Name der Klasse
                                    </label>
                                    <input name="name" id="name" class="form-control" value="{{$klasse->name}}" required>
                                </div>
                                <div class="col-md-4 col-sm-8">
                                    <label for="kuerzel">
                                        Kürzel
                                    </label>
                                    <input name="kuerzel" id="kuerzel" class="form-control" value="{{$klasse->kuerzel}}" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-4 col-sm-12">
                                    <button type="submit" class="btn btn-primary mt-3">Speichern</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
