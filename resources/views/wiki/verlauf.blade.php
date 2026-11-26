@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5" x-data="{ von: null, bis: null }">
    @include('wiki.partials.seitenkopf', ['untertitel' => 'Verlauf'])

    <div class="wiki-karte">
        <div class="wiki-karte-kopf">
            <p class="text-sm text-gray-500">Zwei Fassungen auswählen und vergleichen – oder eine Fassung ansehen und wiederherstellen.</p>
            <a class="wiki-btn wiki-btn-primary wiki-btn-sm" :class="{ 'pointer-events-none opacity-50': !(von && bis) }"
               :href="'{{ route('wiki.vergleich', $seite->slug) }}?von=' + von + '&bis=' + bis"><i class="fas fa-columns"></i> Vergleichen</a>
        </div>
        <div class="wiki-liste">
            @foreach($revisionen as $i => $r)
                <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                    <div class="flex items-center gap-2" title="Für den Vergleich auswählen">
                        <input type="radio" name="von" value="{{ $r->id }}" x-model="von" class="h-4 w-4 accent-blue-600" aria-label="ältere Fassung">
                        <input type="radio" name="bis" value="{{ $r->id }}" x-model="bis" class="h-4 w-4 accent-blue-600" aria-label="neuere Fassung">
                    </div>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('wiki.revision', [$seite->slug, $r]) }}" class="text-sm font-semibold text-gray-900 hover:text-blue-700">{{ $r->created_at?->format('d.m.Y H:i') }}</a>
                        <span class="text-sm text-gray-500">· {{ $r->user->name }}</span>
                        @if($revisionen->currentPage() === 1 && $i === 0)<span class="wiki-badge wiki-badge-blau ml-1">aktuell</span>@endif
                        @if($r->wiederhergestellt_aus)<span class="wiki-badge wiki-badge-grau ml-1"><i class="fas fa-undo"></i> wiederhergestellt</span>@endif
                        @if($r->notiz)<div class="text-sm text-gray-600">{{ $r->notiz }}</div>@endif
                        @if($r->titel !== $seite->titel)<div class="text-xs text-gray-400">Titel damals: {{ $r->titel }}</div>@endif
                    </div>
                    <a href="{{ route('wiki.vergleich', [$seite->slug, 'bis' => $r->id]) }}" class="wiki-btn wiki-btn-ghost wiki-btn-sm">Änderungen</a>
                </div>
            @endforeach
        </div>
    </div>
    {{ $revisionen->links() }}
</div>
@endsection
