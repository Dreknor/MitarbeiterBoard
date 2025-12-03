@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('diagnostic.areas', $schueler->id) }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                    <h4 class="d-inline ml-2">
                        <i class="fas fa-bullseye"></i>
                        {{ $schueler->name }} - Aktuelle Ziele
                    </h4>
                </div>
            </div>

            {{-- Info --}}
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle"></i>
                Diese Übersicht zeigt alle als "aktuell" markierten Ziele des Schülers aus allen Bereichen.
                Dies sind die Ziele, an denen momentan aktiv gearbeitet wird.
            </div>

            {{-- Aktuelle Ziele gruppiert nach Bereich --}}
            @if($currentGoals->isEmpty())
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Keine aktuellen Ziele definiert.
                </div>
            @else
                @foreach($currentGoals->groupBy('goal.stage.area.name') as $areaName => $goals)
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-folder-open"></i> {{ $areaName }}
                            </h5>
                        </div>
                        <div class="card-body">
                            @foreach($goals->groupBy('goal.stage.name') as $stageName => $stageGoals)
                                <div class="mb-3">
                                    <h6 class="border-bottom pb-2">
                                        <i class="fas fa-layer-group"></i> {{ $stageName }}
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width: 80px;">Code</th>
                                                    <th>Beschreibung</th>
                                                    <th style="width: 120px;">Letzte Erfassung</th>
                                                    <th style="width: 100px;" class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($stageGoals as $assessment)
                                                    <tr>
                                                        <td><strong>{{ $assessment->goal->code }}</strong></td>
                                                        <td>{{ $assessment->goal->description }}</td>
                                                        <td>
                                                            <small class="text-muted">
                                                                {{ $assessment->session->session_date->format('d.m.Y') }}
                                                            </small>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-warning">
                                                                <i class="fas fa-crosshairs"></i> Aktuelles Ziel
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Zusammenfassung --}}
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <i class="fas fa-bullseye fa-2x text-primary"></i>
                                </div>
                                <h5>{{ $currentGoals->count() }}</h5>
                                <small class="text-muted">Aktuelle Ziele gesamt</small>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <i class="fas fa-folder-open fa-2x text-success"></i>
                                </div>
                                <h5>{{ $currentGoals->groupBy('goal.stage.area.name')->count() }}</h5>
                                <small class="text-muted">Betroffene Bereiche</small>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <i class="fas fa-layer-group fa-2x text-info"></i>
                                </div>
                                <h5>{{ $currentGoals->groupBy('goal.stage.name')->count() }}</h5>
                                <small class="text-muted">Betroffene Stufen</small>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

