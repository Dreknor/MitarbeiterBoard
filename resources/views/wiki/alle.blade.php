@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    <header class="flex flex-col gap-3">
        <h1 class="wiki-titel">Alle Seiten A–Z</h1>
        <nav class="flex flex-wrap gap-1">
            @foreach($gruppen->keys() as $buchstabe)
                <a href="#buchstabe-{{ $buchstabe === '#' ? 'zahl' : $buchstabe }}" class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-blue-50 hover:text-blue-700">{{ $buchstabe }}</a>
            @endforeach
        </nav>
    </header>

    @forelse($gruppen as $buchstabe => $seiten)
        <section id="buchstabe-{{ $buchstabe === '#' ? 'zahl' : $buchstabe }}" class="wiki-karte" style="scroll-margin-top: 1rem">
            <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel">{{ $buchstabe === '#' ? '0–9 und Sonstige' : $buchstabe }}</h2></div>
            <div class="grid sm:grid-cols-2">
                @foreach($seiten as $s)
                    <a href="{{ $s->url() }}" class="wiki-zeile text-sm text-gray-800"><i class="far fa-file-alt text-gray-400"></i><span class="truncate">{{ $s->titel }}</span></a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="wiki-karte wiki-leer"><i class="fa fa-book"></i>Noch keine Seiten.</div>
    @endforelse
</div>
@endsection
