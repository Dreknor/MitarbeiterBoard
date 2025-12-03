@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('diagnostic.index') }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                    <h4 class="d-inline ml-2">
                        <span class="badge" style="background-color: {{ $klasse->color }}; color: {{ $klasse->text_color }}">
                            {{ $klasse->name }}
                        </span>
                        - Schüler wählen
                    </h4>
                </div>
            </div>

            @if($schueler->isEmpty())
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Keine Schüler in dieser Klasse vorhanden.
                </div>
            @else
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Schülerliste ({{ $schueler->count() }})</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Vorname</th>
                                        <th>Geburtsdatum</th>
                                        <th class="text-right">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schueler as $s)
                                        <tr>
                                            <td>{{ $s->nachname }}</td>
                                            <td>{{ $s->vorname }}</td>
                                            <td>
                                                @if($s->geburtsdatum)
                                                    {{ $s->geburtsdatum->format('d.m.Y') }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('diagnostic.areas', $s->id) }}" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-clipboard-check"></i> Diagnose
                                                </a>
                                                <a href="{{ route('diagnostic.current-goals', $s->id) }}" class="btn btn-info btn-sm">
                                                    <i class="fas fa-bullseye"></i> Ziele
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

