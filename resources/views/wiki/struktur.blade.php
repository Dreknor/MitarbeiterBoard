@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5" x-data="wikiStruktur('{{ route('wiki.struktur.speichern') }}')">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="wiki-titel">Struktur ordnen</h1>
            <p class="wiki-meta mt-1">Seiten per Drag &amp; Drop sortieren und unter andere Seiten ziehen. Erst „Speichern“ übernimmt die Änderungen.</p>
        </div>
        <div class="wiki-aktionen">
            <form method="POST" action="{{ route('wiki.struktur.alphabetisch') }}" onsubmit="return confirm('Manuelle Reihenfolge überall verwerfen und alphabetisch sortieren?')">
                @csrf
                <button class="wiki-btn wiki-btn-secondary wiki-btn-sm"><i class="fas fa-sort-alpha-down"></i> Alphabetisch</button>
            </form>
            <button type="button" class="wiki-btn wiki-btn-primary" @click="speichern()" :disabled="speichert || !geaendert">
                <i class="fas" :class="speichert ? 'fa-spinner fa-spin' : 'fa-check'"></i> Speichern
            </button>
        </div>
    </header>

    <div class="wiki-alert wiki-alert-rot" x-show="fehler" x-cloak><i class="fas fa-exclamation-triangle mt-0.5"></i><div x-text="fehler"></div></div>

    <div class="wiki-karte wiki-karte-inhalt wiki-struktur" x-ref="wurzel">
        @include('wiki.partials.struktur_ebene', ['knoten' => $baum, 'parentId' => null])
    </div>
</div>
@endsection
