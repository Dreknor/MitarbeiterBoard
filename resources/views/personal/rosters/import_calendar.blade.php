@extends('layouts.app')

@section('title') Kalender-Import – Dienstplan @endsection
@section('site-title') Dienstplan: Kalender-Import @endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php([$fensterStart, $fensterEnde] = $roster->department->rosterDayWindow())

@section('content')
<div class="zeit-wrapper max-w-5xl" x-data="{ alle: true }">
    <a href="{{ route('roster.show', $roster->id) }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>zum Dienstplan</a>
    <h1 class="zw-page-title mt-1">Termine aus dem Kalender übernehmen</h1>
    <p class="zw-page-sub mb-4">{{ $roster->department->name }} · Woche {{ $startDate->format('d.m.') }}–{{ $endDate->format('d.m.Y') }}</p>

    <form method="get" action="{{ route('roster.importCalendar.preview', $roster->id) }}" class="zw-card mb-4 px-4 py-3 sm:px-5 flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1">
            <label for="kalender_id" class="zw-label">Kalender</label>
            <select name="kalender_id" id="kalender_id" class="zw-select" onchange="this.form.submit()">
                @forelse($kalender as $kal)
                    <option value="{{ $kal->id }}" @selected($kal->id == $selectedKalenderId)>{{ $kal->name }}</option>
                @empty
                    <option disabled>Keine sichtbaren Kalender verfügbar</option>
                @endforelse
            </select>
        </div>
        <button type="submit" class="zw-btn zw-btn-secondary"><i class="fas fa-sync-alt"></i> Laden</button>
    </form>

    <section class="zw-card">
        @if($termine->isEmpty())
            <div class="zw-empty"><i class="far fa-calendar"></i>
                {{ $selectedKalenderId ? 'Keine Termine in dieser Woche.' : 'Kein Kalender ausgewählt oder keine Kalender verfügbar.' }}
            </div>
        @else
            <form method="post" action="{{ route('roster.importCalendar.store', $roster->id) }}">
                @csrf
                <div class="zw-row border-b border-gray-100 bg-gray-50">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" class="w-4 h-4" x-model="alle" @change="$root.querySelectorAll('input[data-termin]:not([disabled])').forEach(c => c.checked = alle)">
                        Alle auswählen
                    </label>
                </div>
                <ul class="zw-list">
                    @foreach($termine as $termin)
                        @php($schluessel = $termin->selection_key ?? (string) $termin->id)
                        @php($importiert = in_array($schluessel, $bereitsImportiert, true))
                        <li>
                            <label class="zw-row cursor-pointer {{ $importiert ? 'opacity-50' : 'hover:bg-gray-50' }}">
                                <input type="checkbox" name="ox_termin_ids[]" value="{{ $schluessel }}" data-termin class="w-4 h-4 shrink-0" @disabled($importiert) @checked(!$importiert)>
                                <div class="w-28 shrink-0 text-sm">
                                    <div class="font-semibold text-gray-900">{{ $termin->beginn->locale('de')->isoFormat('dd, DD.MM.YYYY') }}</div>
                                    <div class="text-xs text-gray-500">{{ $termin->ganztaegig ? 'ganztägig' : $termin->beginn->format('H:i').'–'.$termin->ende->format('H:i') }}</div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900">{{ $termin->titel }}</div>
                                    <div class="flex flex-wrap gap-1 mt-0.5">
                                        @if($termin->ort)<span class="text-xs text-gray-500"><i class="fas fa-map-marker-alt mr-1"></i>{{ $termin->ort }}</span>@endif
                                        @if($termin->is_recurring)<span class="zw-badge zw-badge-blue">wiederkehrend</span>@endif
                                        @if($importiert)<span class="zw-badge zw-badge-gray">bereits importiert</span>@endif
                                    </div>
                                </div>
                            </label>
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 sm:px-5 border-t border-gray-100">
                    <p class="text-xs text-gray-500">
                        Importierte Termine landen zunächst in der <strong>Merkliste</strong> des Tages und werden dann im Raster zugewiesen.
                        Zeiten werden auf das Tagesfenster {{ $fensterStart }}–{{ $fensterEnde }} Uhr begrenzt.
                    </p>
                    <button type="submit" class="zw-btn zw-btn-primary shrink-0"><i class="fas fa-file-import"></i> Ausgewählte übernehmen</button>
                </div>
            </form>
        @endif
    </section>
</div>
@endsection
