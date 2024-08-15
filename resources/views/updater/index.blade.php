@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-gradient-y2-info">
                <h5>
                    Anwendungsupdater
                </h5>
            </div>
            <div class="card-body">
                Achtung: Dieser Updater soll die Aktualisierung erleichtern. Bitte beachten Sie, dass die Aktualisierung einige Zeit in Anspruch nehmen kann. <br>
                Während des Updates wird die Anwendung in den Wartungsmodus versetzt wodurch kein Zugriff möglich ist. <br>
                <br>
                Vorgehen: <br>
                1. Stellen Sie sicher, dass es ein Backup gibt<br>
                2. Das Update wird zunächst die neuesten Änderungen aus dem GitHub-Repository laden. <br>
                3. Anschließend wird der Cache gelöscht und evtl. notwendige Datenbankmigrationen durchgeführt. <br>
                <br>
                <form action="{{route('updater.update')}}" method="post">
                    @csrf
                    <button type="submit" class="btn btn-primary">Update starten</button>
                </form>
            </div>
        </div>
    </div>
@endsection

