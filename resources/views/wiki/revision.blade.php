@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    @include('wiki.partials.seitenkopf', ['untertitel' => $revision->titel])

    <div class="wiki-alert wiki-alert-blau">
        <i class="fas fa-history mt-0.5"></i>
        <div class="flex-1">
            Fassung vom <strong>{{ $revision->created_at?->format('d.m.Y H:i') }}</strong> von {{ $revision->user->name }}{{ $aktuell ? ' – das ist die aktuelle Fassung.' : '.' }}
            @if($revision->notiz)<div class="text-blue-800/80">„{{ $revision->notiz }}“</div>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if($vorgaenger)
                <a href="{{ route('wiki.vergleich', [$seite->slug, 'von' => $vorgaenger->id, 'bis' => $revision->id]) }}" class="wiki-btn wiki-btn-secondary wiki-btn-sm">Änderungen</a>
            @endif
            @if(! $aktuell)
                @can('update', $seite)
                    <form method="POST" action="{{ route('wiki.wiederherstellen', [$seite, $revision]) }}" onsubmit="return confirm('Diese Fassung wiederherstellen? Sie wird als neue Fassung gespeichert.')">
                        @csrf
                        <button class="wiki-btn wiki-btn-primary wiki-btn-sm"><i class="fas fa-undo"></i> Wiederherstellen</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="wiki-karte" x-data="{ inhaltKlick(e) { if (e.target.tagName === 'IMG') lightbox = e.target.src } }">
        <div class="wiki-karte-inhalt">
            <div class="wiki-inhalt" @click="inhaltKlick($event)">{!! $inhalt !!}</div>
        </div>
    </div>
</div>
@endsection
