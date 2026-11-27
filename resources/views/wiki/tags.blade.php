@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    <h1 class="wiki-titel">Tags</h1>

    @if($tags->isEmpty())
        <div class="wiki-karte wiki-leer"><i class="fas fa-tags"></i>Noch keine Tags. Tags vergeben Sie beim Bearbeiten einer Seite.</div>
    @else
        <div class="wiki-karte wiki-karte-inhalt flex flex-wrap gap-2">
            @foreach($tags as $tag)
                <a href="{{ route('wiki.tag', $tag) }}" class="wiki-tag text-sm">
                    <i class="fas fa-tag text-[10px] opacity-60"></i>{{ $tag->name }}
                    <span class="ml-1 rounded-full bg-white px-1.5 text-[11px] text-gray-500">{{ $tag->anzahl }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
