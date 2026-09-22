@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">
    <div class="thm-card">
        <div class="thm-band thm-band-amber flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-bold"><i class="fas fa-archive mr-1"></i> Archivierte Dateien</h1>
            <span class="thm-badge bg-white/20 text-white">{{ $medien->count() }} Datei(en)</span>
        </div>

        <div class="p-5">
            <p class="text-sm text-gray-500 mb-5">
                Hier entfernte (archivierte) Datei-Anhänge von Themen. Archivierte Dateien bleiben über
                bestehende Protokoll-Verweise abrufbar. Ein endgültiges Löschen ist nur möglich, wenn das
                zugehörige Thema <strong>keine Protokolle</strong> hat – denn ein Protokoll könnte die Datei
                auch ohne Link nur textuell erwähnen („siehe angehängte Datei").
            </p>

            @if($medien->isEmpty())
                <p class="thm-alert thm-alert-info">Keine archivierten Dateien vorhanden.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="thm-table min-w-full">
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
                                        <a href="{{ url('/image/'.$media->id) }}" target="_blank" class="text-blue-600 hover:underline">
                                            <i class="fas fa-file-download"></i> {{ $media->name }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($media->model)
                                            <a href="{{ url($media->model->group->name.'/themes/'.$media->model->id) }}" class="text-blue-600 hover:underline">
                                                {{ \Illuminate\Support\Str::limit($media->model->theme, 40) }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">– gelöscht –</span>
                                        @endif
                                    </td>
                                    <td class="text-gray-600">{{ optional(optional($media->model)->group)->name }}</td>
                                    <td class="text-gray-600">{{ $media->archiviert_von_name ?? '–' }}</td>
                                    <td class="text-gray-600">
                                        {{ $media->getCustomProperty('archiviert_am')
                                            ? \Carbon\Carbon::parse($media->getCustomProperty('archiviert_am'))->format('d.m.Y H:i')
                                            : '–' }}
                                    </td>
                                    <td>
                                        @if($media->geschuetzt)
                                            <span class="thm-badge thm-badge-amber" title="{{ $media->schutzgrund }}">geschützt</span>
                                        @else
                                            <span class="thm-badge thm-badge-gray">frei löschbar</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <form action="{{ route('themes.files.restore', $media->id) }}" method="post">
                                                @csrf @method('PUT')
                                                <button type="submit" class="thm-btn thm-btn-success thm-btn-sm" title="Wiederherstellen">
                                                    <i class="fas fa-undo"></i> <span class="hidden md:inline">Wiederherstellen</span>
                                                </button>
                                            </form>
                                            <form action="{{ route('themes.files.forceDelete', $media->id) }}" method="post"
                                                  onsubmit="return confirm('Diese Datei endgültig und unwiderruflich löschen?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="thm-btn thm-btn-danger thm-btn-sm"
                                                        title="{{ $media->geschuetzt ? $media->schutzgrund.' – kann nicht gelöscht werden' : 'Endgültig löschen' }}"
                                                        @if($media->geschuetzt) disabled @endif>
                                                    <i class="fas fa-trash"></i> <span class="hidden md:inline">Endgültig löschen</span>
                                                </button>
                                            </form>
                                        </div>
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
