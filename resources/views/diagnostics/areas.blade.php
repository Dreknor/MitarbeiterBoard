@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('diagnostic.students', $schueler->klasse_id) }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                    <h4 class="d-inline ml-2">
                        <i class="fas fa-user"></i> {{ $schueler->name }} - Bereich wählen
                    </h4>
                </div>
            </div>

            @if($areas->isEmpty())
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Keine Diagnosebereiche vorhanden. Bitte wenden Sie sich an einen Administrator.
                </div>
            @else
                <div class="row">
                    @foreach($areas as $area)
                        @php
                            $status = $areaStatus[$area->id];
                        @endphp
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100 {{ $status['has_open_session'] ? 'border-warning' : '' }}">
                                <div class="card-header {{ $status['has_open_session'] ? 'bg-warning text-white' : 'bg-light' }}">
                                    <h5 class="mb-0">{{ $area->name }}</h5>
                                </div>
                                <div class="card-body">
                                    @if($area->description)
                                        <p class="card-text small text-muted mb-3">
                                            <strong>Bereichsziel:</strong><br>
                                            {{ $area->description }}
                                        </p>
                                    @endif

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-check-circle text-success"></i>
                                            {{ $status['completed_count'] }} abgeschlossene Durchläufe
                                        </small>
                                    </div>

                                    @if($status['has_open_session'])
                                        <div class="alert alert-warning mb-2 small">
                                            <i class="fas fa-exclamation-circle"></i>
                                            Offene Session vorhanden
                                        </div>
                                        <a href="{{ route('diagnostic.session', $status['open_session']->id) }}" class="btn btn-warning btn-block">
                                            <i class="fas fa-edit"></i> Fortsetzen
                                        </a>
                                    @else
                                        <form action="{{ route('diagnostic.start', [$schueler->id, $area->id]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-block">
                                                <i class="fas fa-play"></i> Neue Diagnose starten
                                            </button>
                                        </form>
                                    @endif

                                    @if($status['completed_count'] > 0)
                                        <a href="{{ route('diagnostic.history', [$schueler->id, $area->id]) }}" class="btn btn-info btn-sm btn-block mt-2">
                                            <i class="fas fa-history"></i> Verlauf anzeigen
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

