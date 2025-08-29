@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5>Schüler bearbeiten</h5>
                </div>
                <div class="card-body">
                    <form action="{{route('schueler.update', $schueler)}}" method="post">
                        @csrf
                        @method('put')
                        <div class="form-row">
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label for="vorname">Vorname</label>
                                <input type="text" name="vorname" id="vorname" class="form-control" value="{{$schueler->vorname}}" required>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label for="nachname">Nachname</label>
                                <input type="text" name="nachname" id="nachname" class="form-control" value="{{$schueler->nachname}}" required>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label for="geburtsdatum">Geburtsdatum</label>
                                <input type="date" name="geburtsdatum" id="geburtsdatum" class="form-control" value="{{$schueler->geburtsdatum?->format('Y-m-d')}}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-6 col-sm-12">
                                <button class="btn btn-primary" type="submit">Speichern</button>
                                <a href="{{url('klassen/'.$schueler->klasse_id.'/edit')}}" class="btn btn-secondary">Zurück</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

