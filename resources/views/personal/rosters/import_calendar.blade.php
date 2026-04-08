@extends('layouts.app')

@section('title') Kalender-Import – Dienstplan #{{$roster->id}} @endsection
@section('site-title') Kalender-Import – Dienstplan #{{$roster->id}} @endsection

@section('content')
<div class="container-fluid">
    <div class="card mb-2">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
                Kalender-Termine importieren für Dienstplan #{{$roster->id}}
                (Woche {{ $startDate->format('d.m.Y') }} – {{ $endDate->format('d.m.Y') }})
            </span>
            <a href="{{ route('roster.show', $roster->id) }}" class="btn btn-sm btn-secondary">
                <i class="la la-arrow-left"></i> Zurück
            </a>
        </div>

        {{-- Kalender-Auswahl --}}
        <div class="card-body border-bottom pb-2">
            <form method="get" action="{{ route('roster.importCalendar.preview', $roster->id) }}" class="form-inline d-flex align-items-center gap-2 flex-wrap">
                <label class="mb-0 font-weight-bold small">Kalender:</label>
                <select name="kalender_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    @forelse($kalender as $kal)
                        <option value="{{ $kal->id }}" @selected($kal->id == $selectedKalenderId)>
                            {{ $kal->name }}
                        </option>
                    @empty
                        <option disabled>Keine sichtbaren Kalender verfügbar</option>
                    @endforelse
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Laden</button>
            </form>
            <p class="small text-muted mt-1 mb-0">
                <i class="la la-info-circle"></i>
                Es werden Termine des gewählten Kalenders im Zeitraum der Dienstplanwoche angezeigt.
                Wiederholungstermine werden nicht berücksichtigt.
            </p>
        </div>

        {{-- Terminliste --}}
        <div class="card-body p-0">
            @if($termine->isEmpty())
                <div class="p-3 text-muted small">
                    @if(!$selectedKalenderId)
                        Kein Kalender ausgewählt oder keine Kalender verfügbar.
                    @else
                        Keine Termine im Zeitraum {{ $startDate->format('d.m.Y') }} – {{ $endDate->format('d.m.Y') }} gefunden.
                    @endif
                </div>
            @else
                <form method="post" action="{{ route('roster.importCalendar.store', $roster->id) }}" id="importForm">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <thead class="thead-light">
                            <tr>
                                <th style="width:38px">
                                    <input type="checkbox" id="checkAll" title="Alle wählen"
                                           style="appearance:auto;-webkit-appearance:checkbox;width:16px;height:16px;cursor:pointer;">
                                </th>
                                <th>Titel</th>
                                <th>Datum</th>
                                <th>Uhrzeit</th>
                                <th>Ort</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($termine as $termin)
                                @php($schonImportiert = in_array($termin->id, $bereitsImportiert))
                                <tr class="{{ $schonImportiert ? 'table-secondary' : '' }}">
                                    <td>
                                        <input type="checkbox"
                                               name="ox_termin_ids[]"
                                               value="{{ $termin->id }}"
                                               class="row-select"
                                               @disabled($schonImportiert)
                                               @checked(!$schonImportiert)
                                               style="appearance:auto;-webkit-appearance:checkbox;width:16px;height:16px;cursor:pointer;">
                                    </td>
                                    <td>
                                        {{ $termin->titel }}
                                        @if($termin->ganztaegig)
                                            <span class="badge badge-info ml-1" title="Ganztägiger Termin – wird als 08:00–14:30 importiert">ganztägig</span>
                                        @endif
                                        @if($schonImportiert)
                                            <span class="badge badge-secondary ml-1" title="Bereits in diesen Dienstplan importiert">bereits importiert</span>
                                        @endif
                                    </td>
                                    <td>{{ $termin->beginn->format('d.m.Y') }}</td>
                                    <td>
                                        @if($termin->ganztaegig)
                                            <span class="text-muted">ganztägig → 08:00–14:30</span>
                                        @else
                                            {{ $termin->beginn->format('H:i') }} – {{ $termin->ende->format('H:i') }}
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $termin->ort ?? '—' }}</td>
                                    <td class="small text-muted">{{ $termin->status ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="small text-muted">
                            Nur markierte Termine werden importiert. Bereits importierte Termine werden übersprungen.
                            Ganztägige Termine werden als 08:00–14:30 Uhr angelegt.
                            Importierte Termine sind zunächst <strong>nicht zugewiesen</strong> (Merkzettel).
                        </div>
                        <button type="submit" class="btn btn-success btn-sm"
                                onclick="return document.querySelectorAll('.row-select:checked').length > 0 || (alert('Bitte mindestens einen Termin auswählen.'), false)">
                            <i class="la la-calendar-check-o"></i> Ausgewählte importieren
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    document.getElementById('checkAll')?.addEventListener('change', function () {
        document.querySelectorAll('.row-select:not(:disabled)').forEach(cb => cb.checked = this.checked);
    });
</script>
@endpush

