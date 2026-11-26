@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    <header>
        <h1 class="wiki-titel">Papierkorb</h1>
        <p class="wiki-meta mt-1">Gelöschte Seiten bleiben mit ihrem ganzen Verlauf erhalten und lassen sich wiederherstellen.</p>
    </header>

    <div class="wiki-karte wiki-liste">
        @forelse($seiten as $s)
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('wiki.show', $s->slug) }}" class="text-sm font-semibold text-gray-900 hover:text-blue-700">{{ $s->titel }}</a>
                    <div class="text-xs text-gray-400">gelöscht {{ $s->deleted_at?->format('d.m.Y H:i') }}{{ $s->geloescht_von_name ? ' von '.$s->geloescht_von_name : '' }}</div>
                </div>
                <form method="POST" action="{{ route('wiki.papierkorb.wiederherstellen', $s->id) }}">
                    @csrf
                    <button class="wiki-btn wiki-btn-secondary wiki-btn-sm"><i class="fas fa-undo"></i> Wiederherstellen</button>
                </form>
            </div>
        @empty
            <div class="wiki-leer"><i class="fas fa-trash-alt"></i>Der Papierkorb ist leer.</div>
        @endforelse
    </div>
</div>
@endsection
