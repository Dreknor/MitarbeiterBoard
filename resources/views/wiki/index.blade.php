@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    <section class="wiki-karte" data-tour="wiki-einleitung">
        <div class="wiki-karte-inhalt">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="wiki-titel">Willkommen im Wiki</h1>
                    <p class="wiki-meta mt-1">{{ $anzahl }} {{ $anzahl === 1 ? 'Seite' : 'Seiten' }} · Anleitungen, Abläufe und Regelungen der Schule</p>
                </div>
                @if($start)
                    @can('update', $start)
                        <a href="{{ route('wiki.edit', $start->slug) }}" class="wiki-btn wiki-btn-secondary wiki-btn-sm"><i class="fas fa-pen"></i> Einleitung bearbeiten</a>
                    @endcan
                @endif
            </div>
            @if($einleitung !== '')
                <div class="wiki-inhalt mt-4">{!! $einleitung !!}</div>
            @endif
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="wiki-karte" data-tour="wiki-favoriten">
            <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-star text-amber-400"></i> Meine Favoriten</h2></div>
            @if($favoriten->isEmpty())
                <div class="wiki-leer"><i class="far fa-star"></i>Mit dem Stern oben auf einer Seite merken Sie sich Seiten, die Sie oft brauchen.</div>
            @else
                <div class="wiki-liste">
                    @foreach($favoriten as $f)
                        <a href="{{ $f->url() }}" class="wiki-zeile">
                            <i class="fas fa-star text-amber-400 text-sm"></i>
                            <span class="flex-1 min-w-0 truncate text-sm font-medium text-gray-800">{{ $f->titel }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="wiki-karte">
            <div class="wiki-karte-kopf">
                <h2 class="wiki-karte-titel"><i class="fas fa-history"></i> Zuletzt geändert</h2>
                <a href="{{ route('wiki.aenderungen') }}" class="text-sm font-semibold text-blue-700 hover:underline">Alle Änderungen</a>
            </div>
            @if($zuletzt->isEmpty())
                <div class="wiki-leer"><i class="far fa-file-alt"></i>Noch keine Seiten.</div>
            @else
                <div class="wiki-liste">
                    @foreach($zuletzt as $s)
                        <a href="{{ $s->url() }}" class="wiki-zeile">
                            <i class="far fa-file-alt text-gray-400 text-sm"></i>
                            <span class="flex-1 min-w-0 truncate text-sm font-medium text-gray-800">{{ $s->titel }}</span>
                            <span class="shrink-0 text-xs text-gray-400" title="{{ $s->bearbeitet_at?->format('d.m.Y H:i') }}">{{ $s->bearbeitet_at?->diffForHumans() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <section>
        <h2 class="text-lg font-bold text-gray-900 mb-3">Bereiche</h2>
        @if($bereiche->isEmpty())
            <div class="wiki-karte wiki-leer"><i class="fa fa-book"></i>Das Wiki ist noch leer.@if($wikiDarfAnlegen) <a href="{{ route('wiki.create') }}" class="font-semibold text-blue-700">Erste Seite anlegen</a>@endif</div>
        @else
            <div class="wiki-bereiche">
                @foreach($bereiche as $b)
                    <div class="wiki-bereich">
                        <a href="{{ $b['url'] }}" class="wiki-bereich-titel">
                            <i class="fas {{ empty($b['kinder']) ? 'fa-file-alt' : 'fa-folder-open' }}"></i>
                            <span class="min-w-0 truncate">{{ $b['titel'] }}</span>
                            @if($b['eingeschraenkt'])<span class="wiki-badge wiki-badge-gelb ml-auto"><i class="fas fa-lock"></i></span>@endif
                        </a>
                        @if(! empty($b['kinder']))
                            <ul>
                                @foreach(array_slice($b['kinder'], 0, 6) as $k)
                                    <li><a href="{{ $k['url'] }}">{{ $k['titel'] }}</a></li>
                                @endforeach
                                @if(count($b['kinder']) > 6)
                                    <li><a href="{{ $b['url'] }}" class="font-semibold text-blue-700">+ {{ count($b['kinder']) - 6 }} weitere</a></li>
                                @endif
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>

@endsection
