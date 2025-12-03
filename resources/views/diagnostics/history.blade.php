@extends('layouts.app')
@endsection
</div>
    </div>
        </div>
            @endif
                </div>
                    </div>
                        {{-- <canvas id="progressChart"></canvas> --}}
                        {{-- Hier kommt später Chart.js --}}
                        </div>
                            Grafische Auswertung wird in einer zukünftigen Version verfügbar sein.
                            <i class="fas fa-info-circle"></i>
                        <div class="alert alert-info">
                    <div class="card-body">
                    </div>
                        </h5>
                            <i class="fas fa-chart-line"></i> Fortschrittsübersicht
                        <h5 class="mb-0">
                    <div class="card-header">
                <div class="card">
            @if($sessions->count() > 1)
            {{-- Grafische Auswertung (Platzhalter für zukünftige Chart.js Integration) --}}

            </div>
                </div>
                    @endif
                        </div>
                            </table>
                                </tbody>
                                    @endforeach
                                        </tr>
                                            </td>
                                                </a>
                                                    <i class="fas fa-eye"></i> Ansehen
                                                <a href="{{ route('diagnostic.session', $session->id) }}" class="btn btn-sm btn-primary">
                                            <td class="text-right">
                                            <td>{{ $session->assessments->count() }} Ziele</td>
                                            <td>{{ $session->user->name }}</td>
                                            </td>
                                                @endif
                                                    <span class="badge badge-warning">In Bearbeitung</span>
                                                @else
                                                    <span class="badge badge-success">Abgeschlossen</span>
                                                @if($session->is_completed)
                                            <td>
                                            <td>{{ $session->session_date->format('d.m.Y') }}</td>
                                        <tr>
                                    @foreach($sessions as $session)
                                <tbody>
                                </thead>
                                    </tr>
                                        <th class="text-right">Aktionen</th>
                                        <th>Bewertete Ziele</th>
                                        <th>Ersteller</th>
                                        <th>Status</th>
                                        <th>Datum</th>
                                    <tr>
                                <thead>
                            <table class="table table-hover">
                        <div class="table-responsive">
                    @else
                        </div>
                            Noch keine Erfassungen für diesen Bereich vorhanden.
                        <div class="alert alert-info">
                    @if($sessions->isEmpty())
                <div class="card-body">
                </div>
                    </h5>
                        <i class="fas fa-calendar-alt"></i> Durchgeführte Erfassungen ({{ $sessions->count() }})
                    <h5 class="mb-0">
                <div class="card-header">
            <div class="card mb-3">
            {{-- Übersicht Sessions --}}

            </div>
                </div>
                    </h4>
                        {{ $schueler->name }} - {{ $area->name }} - Verlauf
                        <i class="fas fa-history"></i>
                    <h4 class="d-inline ml-2">
                    </a>
                        <i class="fas fa-arrow-left"></i> Zurück
                    <a href="{{ route('diagnostic.areas', $schueler->id) }}" class="btn btn-sm btn-secondary">
                <div>
            <div class="d-flex justify-content-between align-items-center mb-3">
            {{-- Header --}}
        <div class="col-12">
    <div class="row">
<div class="container-fluid">
@section('content')


