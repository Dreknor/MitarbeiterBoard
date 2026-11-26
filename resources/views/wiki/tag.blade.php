@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    <header>
        <a href="{{ route('wiki.tags') }}" class="text-sm text-gray-500 hover:text-blue-700"><i class="fas fa-arrow-left"></i> Alle Tags</a>
        <h1 class="wiki-titel mt-1"><i class="fas fa-tag text-gray-300 text-xl"></i> {{ $tag->name }}</h1>
    </header>

    <div class="wiki-karte wiki-liste">
        @forelse($seiten as $s)
            <a href="{{ $s->url() }}" class="wiki-zeile text-sm font-medium text-gray-800"><i class="far fa-file-alt text-gray-400"></i> {{ $s->titel }}</a>
        @empty
            <div class="wiki-leer"><i class="far fa-file"></i>Keine sichtbaren Seiten mit diesem Tag.</div>
        @endforelse
    </div>

    @can('manage wiki')
        <section class="wiki-karte">
            <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-cog"></i> Tag verwalten</h2></div>
            <div class="wiki-karte-inhalt grid gap-4 md:grid-cols-3">
                <form method="POST" action="{{ route('wiki.tags.umbenennen', $tag) }}" class="flex flex-col gap-2">
                    @csrf @method('PUT')
                    <label class="wiki-label" for="tag-name">Umbenennen</label>
                    <input id="tag-name" name="name" class="wiki-input" value="{{ $tag->name }}" maxlength="40" required>
                    <div><button class="wiki-btn wiki-btn-secondary wiki-btn-sm">Umbenennen</button></div>
                </form>
                <form method="POST" action="{{ route('wiki.tags.zusammenfuehren', $tag) }}" class="flex flex-col gap-2">
                    @csrf
                    <label class="wiki-label" for="tag-ziel">Zusammenführen mit</label>
                    <select id="tag-ziel" name="ziel" class="wiki-select" required>
                        <option value="">– Tag wählen –</option>
                        @foreach($alleTags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                    <div><button class="wiki-btn wiki-btn-secondary wiki-btn-sm">Zusammenführen</button></div>
                </form>
                <form method="POST" action="{{ route('wiki.tags.loeschen', $tag) }}" class="flex flex-col gap-2" onsubmit="return confirm('Tag von allen Seiten entfernen und löschen?')">
                    @csrf @method('DELETE')
                    <span class="wiki-label">Löschen</span>
                    <p class="text-xs text-gray-500">Entfernt den Tag von allen Seiten. Die Seiten bleiben erhalten.</p>
                    <div><button class="wiki-btn wiki-btn-danger wiki-btn-sm">Tag löschen</button></div>
                </form>
            </div>
        </section>
    @endcan
</div>
@endsection
