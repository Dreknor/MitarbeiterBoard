@extends('layouts.app')

@section('title', 'Geburtstage importieren – Vorschau')

@push('css')
    @vite(['resources/css/users-import.css'])
@endpush

@php $anzahlSetzen = count($gruppen[\App\Services\Personal\GeburtstagImportService::SETZEN]); @endphp

@section('content')
<div class="uim-wrapper">
    <div class="mb-5">
        <a href="{{ route('users.geburtstage.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Andere Datei wählen</a>
        <h1 class="text-xl mt-1">Vorschau: {{ $dateiname }}</h1>
        <p class="text-sm text-gray-600 mt-1">
            {{ $anzahl }} Zeile(n) gelesen. Gespeichert wird erst mit „Übernehmen“; bereits gesetzte Geburtstage bleiben unverändert.
        </p>
    </div>

    <div class="uim-card p-4 mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm">
            @if($anzahlSetzen > 0)
                <strong>{{ $anzahlSetzen }}</strong> Geburtstag(e) werden gesetzt.
            @else
                Es gibt keinen Geburtstag, der gesetzt werden kann.
            @endif
        </p>
        <div class="flex gap-2">
            <a href="{{ route('users.geburtstage.index') }}" class="uim-btn uim-btn-secondary">Abbrechen</a>
            @if($anzahlSetzen > 0)
                <form action="{{ route('users.geburtstage.store', $token) }}" method="post">
                    @csrf
                    <button type="submit" class="uim-btn uim-btn-primary">{{ $anzahlSetzen }} übernehmen</button>
                </form>
            @endif
        </div>
    </div>

    @include('users.geburtstage._gruppen', ['gruppen' => $gruppen, 'bericht' => false])
</div>
@endsection
