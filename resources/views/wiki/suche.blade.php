@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    <header>
        <h1 class="wiki-titel">Suche</h1>
        @if($begriff !== '')
            <p class="wiki-meta mt-1">{{ count($treffer) }} {{ count($treffer) === 1 ? 'Treffer' : 'Treffer' }} für „{{ $begriff }}“</p>
        @endif
    </header>

    @if($begriff === '' || mb_strlen($begriff) < 2)
        <div class="wiki-karte wiki-leer"><i class="fas fa-search"></i>Bitte mindestens zwei Zeichen eingeben.</div>
    @elseif(empty($treffer))
        <div class="wiki-karte wiki-leer">
            <i class="far fa-frown"></i>Keine Seite gefunden.
            @can('create', \App\Models\WikiSeite::class)
                <div class="mt-3"><a href="{{ route('wiki.create', ['titel' => $begriff]) }}" class="wiki-btn wiki-btn-primary wiki-btn-sm"><i class="fas fa-plus"></i> Seite „{{ $begriff }}“ anlegen</a></div>
            @endcan
        </div>
    @else
        <div class="wiki-karte wiki-liste">
            @foreach($treffer as $t)
                <a href="{{ $t['url'] }}" class="block px-4 py-3 sm:px-5 hover:bg-gray-50">
                    @if($t['pfad'] !== '')<div class="text-xs text-gray-400">{{ $t['pfad'] }}</div>@endif
                    <div class="text-base font-semibold text-blue-800">{{ $t['titel'] }}</div>
                    @if($t['snippet'] !== '')<div class="mt-0.5 text-sm text-gray-600">{!! $t['snippet'] !!}</div>@endif
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-400">
                        @foreach($t['tags'] as $tag)<span class="wiki-badge wiki-badge-grau">{{ $tag }}</span>@endforeach
                        @if($t['geaendert'])<span>geändert {{ $t['geaendert'] }}</span>@endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
