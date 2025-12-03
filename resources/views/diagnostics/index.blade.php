@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>
                    <i class="fas fa-clipboard-check"></i> Diagnosebögen
                </h4>
                @can('manage diagnostics')
                    <a href="{{ route('diagnostic.admin.index') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-cog"></i> Verwaltung
                    </a>
                @endcan
            </div>

            @if($klassen->isEmpty())
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Sie haben keine Klassen für Diagnosebögen zugewiesen.
                </div>
            @else
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Klasse wählen</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($klassen as $klasse)
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100 border-left-primary shadow-sm">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <span class="badge" style="background-color: {{ $klasse->color }}; color: {{ $klasse->text_color }}">
                                                    {{ $klasse->name }}
                                                </span>
                                            </h5>
                                            <p class="card-text text-muted small">
                                                <i class="fas fa-users"></i> {{ $klasse->schueler_count }} Schüler
                                            </p>
                                            <a href="{{ route('diagnostic.students', $klasse->id) }}" class="btn btn-primary btn-sm btn-block">
                                                <i class="fas fa-arrow-right"></i> Zur Klasse
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

