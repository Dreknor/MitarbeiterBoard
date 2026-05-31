@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0">
                    <i class="fas fa-archive"></i>
                    Archivierte Dateien
                </h5>
                <small class="text-muted">{{ $medien->count() }} Datei(en)</small>
            </div>

            <div class="card-body">
                <p class="text-muted">
                    Hier entfernte (archivierte) Datei-Anhänge von Themen. Archivierte Dateien bleiben
                    über bestehende Protokoll-Verweise abrufbar. Ein endgültiges Löschen ist nur möglich,
                    wenn das zugehörige Thema <strong>keine Protokolle</strong> hat – denn ein Protokoll
                    könnte die Datei auch ohne Link nur textuell erwähnen („siehe angehängte Datei").
                </p>

                @if($medien->isEmpty())
                    <div class="alert alert-info mb-0">
                        Keine archivierten Dateien vorhanden.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Datei</th>
                                    <th>Thema</th>
                                    <th>Gruppe</th>
                                    <th>Archiviert von</th>
                                    <th>Archiviert am</th>
                                    <th>Status</th>
                                    <th class="text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($medien as $media)
                                    <tr>
                                        <td>
                                            <a href="{{ url('/image/'.$media->id) }}" target="_blank">
                                                <i class="fas fa-file-download"></i>
                                                {{ $media->name }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($media->model)
                                                <a href="{{ url($media->model->group->name.'/themes/'.$media->model->id) }}">
                                                    {{ \Illuminate\Support\Str::limit($media->model->theme, 40) }}
                                                </a>
                                            @else
                                                <span class="text-muted">– gelöscht –</span>
                                            @endif
                                        </td>
                                        <td>{{ optional(optional($media->model)->group)->name }}</td>
                                        <td>{{ $media->archiviert_von_name ?? '–' }}</td>
                                        <td>
                                            {{ $media->getCustomProperty('archiviert_am')
                                                ? \Carbon\Carbon::parse($media->getCustomProperty('archiviert_am'))->format('d.m.Y H:i')
                                                : '–' }}
                                        </td>
                                        <td>
                                            @if($media->geschuetzt)
                                                <span class="badge badge-warning" title="{{ $media->schutzgrund }}">geschützt</span>
                                            @else
                                                <span class="badge badge-secondary">frei löschbar</span>
                                            @endif
                                        </td>
                                        <td class="text-right text-nowrap">
                                            {{-- Wiederherstellen --}}
                                            <form action="{{ route('themes.files.restore', $media->id) }}" method="post" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Wiederherstellen">
                                                    <i class="fas fa-undo"></i>
                                                    <span class="d-none d-md-inline">Wiederherstellen</span>
                                                </button>
                                            </form>

                                            {{-- Endgültig löschen (nur wenn nicht geschützt) --}}
                                            <form action="{{ route('themes.files.forceDelete', $media->id) }}" method="post" class="d-inline"
                                                  onsubmit="return confirm('Diese Datei endgültig und unwiderruflich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="{{ $media->geschuetzt ? $media->schutzgrund . ' – kann nicht gelöscht werden' : 'Endgültig löschen' }}"
                                                        @if($media->geschuetzt) disabled @endif>
                                                    <i class="fas fa-trash"></i>
                                                    <span class="d-none d-md-inline">Endgültig löschen</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

