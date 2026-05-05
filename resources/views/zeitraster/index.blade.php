@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-11 mx-auto">

            {{-- Flash-Meldung --}}
            @if(session('Meldung'))
                <div class="alert alert-{{ session('type', 'info') }} alert-dismissible fade show mt-3" role="alert">
                    {{ session('Meldung') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center my-3">
                <div>
                    <a href="{{ url('rooms/rooms') }}" class="btn btn-sm btn-outline-secondary mr-2">
                        <i class="fa fa-arrow-left"></i> Zurück zum Raumplan
                    </a>
                    <h4 class="d-inline mb-0">Zeitraster-Verwaltung</h4>
                </div>
                @can('manage zeitraster')
                    <a href="{{ route('zeitraster.create') }}" class="btn btn-primary">
                        <i class="fa fa-plus"></i> Neues Zeitraster
                    </a>
                @endcan
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Beschreibung</th>
                                <th class="text-center">Stunden</th>
                                <th class="text-center">Klassen</th>
                                <th class="text-center">Status</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($zeitraster as $zr)
                                <tr>
                                    <td>
                                        <strong>{{ $zr->name }}</strong>
                                    </td>
                                    <td class="text-muted small">
                                        {{ \Illuminate\Support\Str::limit($zr->beschreibung, 60) ?: '–' }}
                                    </td>
                                    <td class="text-center">{{ $zr->lesson_times_count }}</td>
                                    <td class="text-center">{{ $zr->klassen_count }}</td>
                                    <td class="text-center">
                                        @if($zr->ist_standard)
                                            <span class="badge badge-primary">Standard</span>
                                        @endif
                                    </td>
                                    <td>
                                        @can('manage zeitraster')
                                            <a href="{{ route('zeitraster.edit', $zr) }}"
                                               class="btn btn-sm btn-outline-secondary">Bearbeiten</a>

                                            @if(!$zr->ist_standard)
                                                {{-- Als Standard setzen --}}
                                                <form action="{{ route('zeitraster.markStandard', $zr) }}"
                                                      method="post" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                                            title="Als Standard-Zeitraster festlegen">
                                                        Als Standard
                                                    </button>
                                                </form>

                                                {{-- Löschen nur wenn keine Klassen zugeordnet --}}
                                                @if($zr->klassen_count === 0)
                                                    <form action="{{ route('zeitraster.destroy', $zr) }}"
                                                          method="post" class="d-inline"
                                                          onsubmit="return confirm('Zeitraster {{ addslashes($zr->name) }} wirklich löschen?');">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            Löschen
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Noch keine Zeitraster vorhanden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

