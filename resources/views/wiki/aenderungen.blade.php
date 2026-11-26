@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    <h1 class="wiki-titel">Letzte Änderungen</h1>

    <div class="wiki-karte wiki-liste">
        @forelse($revisionen as $r)
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                <span class="w-28 shrink-0 text-xs text-gray-400">{{ $r->created_at?->format('d.m.Y H:i') }}</span>
                <div class="min-w-0 flex-1">
                    @if($r->seite)
                        <a href="{{ $r->seite->url() }}" class="text-sm font-semibold text-gray-900 hover:text-blue-700">{{ $r->seite->titel }}</a>
                    @endif
                    <span class="text-sm text-gray-500">· {{ $r->user->name }}</span>
                    @if($r->notiz)<div class="text-sm text-gray-600">{{ $r->notiz }}</div>@endif
                </div>
                @if($r->seite)
                    <a href="{{ route('wiki.vergleich', [$r->seite->slug, 'bis' => $r->id]) }}" class="wiki-btn wiki-btn-ghost wiki-btn-sm">Änderungen</a>
                @endif
            </div>
        @empty
            <div class="wiki-leer"><i class="fas fa-history"></i>Noch keine Änderungen.</div>
        @endforelse
    </div>
    {{ $revisionen->links() }}
</div>
@endsection
