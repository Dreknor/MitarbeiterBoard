@extends('layouts.app')

@section('title', 'Geburtstage importieren')

@push('css')
    @vite(['resources/css/users-import.css'])
@endpush

@section('content')
<div class="uim-wrapper">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <a href="{{ url('users') }}" class="text-sm text-gray-500 hover:text-gray-700">← Benutzerkonten</a>
            <h1 class="text-xl mt-1">Geburtstage importieren</h1>
        </div>
        <a href="{{ route('users.geburtstage.vorlage') }}" class="uim-btn uim-btn-secondary">Beispieldatei herunterladen</a>
    </div>

    @php $bericht = session('geburtstag_import_bericht'); @endphp
    @if($bericht)
        <div class="mb-6">
            <h2 class="mb-3">Ergebnis für „{{ $bericht['dateiname'] }}“</h2>
            @include('users.geburtstage._gruppen', ['gruppen' => $bericht['gruppen'], 'bericht' => true])
        </div>
    @endif

    <div class="uim-card p-5 flex flex-col gap-4">
        <div class="text-sm text-gray-700 flex flex-col gap-1">
            <p>Die Datei braucht eine Kopfzeile mit den Spalten <strong>E-Mail</strong> und <strong>Geburtstag</strong> (oder <em>Geburtsdatum</em>). Weitere Spalten werden ignoriert.</p>
            <p>Zugeordnet wird über die E-Mail-Adresse des Benutzerkontos. Bereits gespeicherte Geburtstage werden <strong>nie überschrieben</strong>.</p>
            <p>Datumsangaben als Excel-Datum, <code>TT.MM.JJJJ</code> oder <code>JJJJ-MM-TT</code>. Höchstens {{ $maxZeilen }} Zeilen.</p>
            <p>Vor dem Speichern erscheint eine Vorschau.</p>
        </div>

        <form action="{{ route('users.geburtstage.vorschau') }}" method="post" enctype="multipart/form-data" class="flex flex-col gap-3">
            @csrf
            <input type="file" name="datei" accept=".xlsx,.xls,.ods" required class="uim-file">
            <div>
                <button type="submit" class="uim-btn uim-btn-primary">Datei prüfen</button>
            </div>
        </form>
    </div>
</div>
@endsection
