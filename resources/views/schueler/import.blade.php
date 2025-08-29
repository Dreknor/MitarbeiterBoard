@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-10 mx-auto">
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Schüler Import</h5>
                    <a href="{{url('klassen')}}" class="btn btn-secondary btn-sm">Zurück</a>
                </div>
                <div class="card-body">
                    <p>Datei (xlsx/csv) mit Spalten: <code>import_key, vorname, nachname, geburtsdatum(optional), klasse</code>.<br>klasse = Klassenname oder Kürzel. Wenn leer → Schüler wird (bzw. bleibt) archiviert. Nicht mehr vorkommende import_key werden archiviert.</p>
                    <form action="{{route('schueler.import')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label for="file">Datei</label>
                                <input type="file" name="file" id="file" class="form-control" required>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit">Import starten</button>
                    </form>
                    <hr>
                    <p>Beispiel CSV:</p>
<pre class="bg-light p-2 small">import_key,vorname,nachname,geburtsdatum,klasse
1001,Max,Muster,2012-06-01,5a
1002,Lisa,Beispiel,2011-11-12,5a
1003,Tom,Test,,5b
1004,Sara,Alt,,  # bleibt archiviert (kein Klassenwert)
</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

