@extends('layouts.app')

@section('title', 'Klassenraum-Tools')
@section('site-title', 'Klassenraum-Tools')

@push('css')
    @vite(['resources/css/klassenraum.css'])
@endpush

@section('content')
<div class="kr-wrapper">

    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Klassenraum-Tools</h1>
        <p class="text-sm text-gray-500 mt-1 max-w-3xl">
            Werkzeuge für Beamer, Smartboard und Tafel-PC. Jedes Tool öffnet sich als eigene Vollbildseite –
            am besten in einem eigenen Fenster auf dem Präsentationsbildschirm.
        </p>
    </div>

    @foreach($kategorien as $schluessel => $kategorie)
        <section class="mb-8" aria-labelledby="kr-kategorie-{{ $schluessel }}">
            <div class="mb-3">
                <h2 id="kr-kategorie-{{ $schluessel }}" class="text-base font-bold text-gray-800">{{ $kategorie['titel'] }}</h2>
                <p class="text-sm text-gray-500 mt-0.5 max-w-3xl">{{ $kategorie['beschreibung'] }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($kategorie['tools'] as $toolSchluessel => $tool)
                    <article class="kr-karte" style="--kr-farbe: {{ $tool['farbe'] }}">
                        <a href="{{ route('klassenraum.show', $toolSchluessel) }}" class="kr-karte-bild" aria-hidden="true" tabindex="-1">
                            <span class="kr-karte-symbol"><i class="{{ $tool['icon'] }}"></i></span>
                        </a>
                        <div class="kr-karte-inhalt">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-gray-900">{{ $tool['titel'] }}</h3>
                                @if($tool['mikrofon'])
                                    <span class="kr-marke" title="Nutzt das Mikrofon"><i class="fas fa-microphone"></i> Mikrofon</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $tool['beschreibung'] }}</p>
                        </div>
                        <div class="kr-karte-fuss">
                            <a href="{{ route('klassenraum.show', $toolSchluessel) }}" class="kr-btn kr-btn-primary">
                                <i class="fas fa-play"></i> Öffnen
                            </a>
                            <a href="{{ route('klassenraum.show', $toolSchluessel) }}" target="_blank" rel="noopener" class="kr-btn kr-btn-secondary"
                               title="{{ $tool['titel'] }} in einem neuen Fenster öffnen">
                                <i class="fas fa-external-link-alt"></i> Neues Fenster
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="kr-hinweis" aria-label="Hinweise">
        <i class="fas fa-info-circle"></i>
        <div class="text-sm text-gray-600">
            <p><b class="text-gray-800">Mikrofon und Datenschutz:</b>
                Die Lautstärke-Tools werten das Mikrofon ausschließlich live im Browser aus. Es wird nichts aufgenommen,
                gespeichert oder an den Server übertragen. Der Browser fragt beim ersten Start nach der Erlaubnis;
                das funktioniert nur, wenn das MitarbeiterBoard über <code>https</code> aufgerufen wird.</p>
            <p class="mt-2"><b class="text-gray-800">Tipp:</b>
                Grenze und Empfindlichkeit einmal pro Raum einstellen – die Werte merkt sich der Browser für jedes Tool.
                Mit <kbd>F</kbd> geht es in den Vollbildmodus.</p>
        </div>
    </section>
</div>
@endsection
