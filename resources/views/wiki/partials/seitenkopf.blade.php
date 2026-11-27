{{-- Kopf für Unterseiten einer Wiki-Seite (Verlauf, Vergleich, Einstellungen). $seite, $pfad, $untertitel --}}
<header class="flex flex-col gap-1">
    <nav class="wiki-brotkrumen" aria-label="Pfad">
        <a href="{{ route('wiki') }}"><i class="fas fa-home text-xs text-gray-400"></i></a>
        @foreach($pfad as $p)
            <i class="fas fa-chevron-right"></i>
            <a href="{{ $p->url() }}">{{ $p->titel }}</a>
        @endforeach
        <i class="fas fa-chevron-right"></i>
        <a href="{{ $seite->url() }}">{{ $seite->titel }}</a>
    </nav>
    <h1 class="wiki-titel">{{ $untertitel }}</h1>
</header>
