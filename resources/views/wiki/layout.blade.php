{{--
    Rahmen aller Wiki-Seiten: Kopfleiste mit Live-Suche (Strg+K oder „/“), Navigation mit Seitenbaum,
    Lightbox und Hinweis-Toast. Inhalt kommt aus @section('wiki'). Daten: App\View\Composers\WikiLayoutComposer.
--}}
@extends('layouts.app')

@section('title', 'Wiki')

@push('css')
    @vite(['resources/css/wiki.css', 'resources/js/wiki.js'])
@endpush

@section('content')
<div class="wiki-wrapper" x-data="wikiRahmen()" @keydown.window="tastatur($event)" @wiki-toast.window="toast($event.detail)">
    <div class="wiki-kopf wiki-nicht-drucken">
        <button type="button" class="wiki-btn wiki-btn-secondary wiki-btn-icon wiki-nav-umschalter" @click="navOffen = !navOffen" :aria-expanded="navOffen" aria-label="Navigation ein-/ausblenden">
            <i class="fas fa-bars"></i>
        </button>
        <a href="{{ route('wiki') }}" class="wiki-marke"><i class="fa fa-book"></i> Wiki</a>

        <div class="wiki-suchfeld" x-data="wikiSuche('{{ route('wiki.suche') }}')" @click.outside="offen = false" data-tour="wiki-suche">
            <i class="fas fa-search"></i>
            <form action="{{ route('wiki.suche') }}" method="GET" role="search" @submit="absenden($event)">
                <input type="search" name="q" x-ref="eingabe" x-model="begriff" @input.debounce.200ms="suchen()" @focus="offen = treffer.length > 0"
                       @keydown.arrow-down.prevent="weiter(1)" @keydown.arrow-up.prevent="weiter(-1)" @keydown.escape="offen = false; $refs.eingabe.blur()"
                       placeholder="Wiki durchsuchen …" autocomplete="off" aria-label="Wiki durchsuchen" value="{{ request()->routeIs('wiki.suche') ? request('q') : '' }}">
            </form>
            <span class="wiki-kbd" x-show="!begriff">Strg K</span>
            <div class="wiki-vorschlaege" x-show="offen" x-cloak>
                <template x-for="(t, i) in treffer" :key="t.id">
                    <a :href="t.url" class="wiki-vorschlag" :class="{ 'is-aktiv': i === aktiv }" @mouseenter="aktiv = i">
                        <div class="wiki-vorschlag-pfad" x-show="t.pfad" x-text="t.pfad"></div>
                        <div class="wiki-vorschlag-titel" x-text="t.titel"></div>
                        <div class="wiki-vorschlag-text" x-html="t.snippet"></div>
                    </a>
                </template>
                <div class="px-4 py-3 text-sm text-gray-500" x-show="!laedt && begriff.length >= 2 && treffer.length === 0">Keine Treffer.</div>
                <a :href="'{{ route('wiki.suche') }}?q=' + encodeURIComponent(begriff)" class="block px-4 py-2 text-xs font-semibold text-blue-700 bg-gray-50 hover:bg-blue-50" x-show="treffer.length > 0">
                    Alle Ergebnisse anzeigen <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <button type="button" class="wiki-btn wiki-btn-ghost wiki-btn-icon" data-tour-start="wiki" title="Kurze Einführung"><i class="far fa-question-circle"></i></button>
            @if($wikiDarfAnlegen)
                <a href="{{ route('wiki.create', array_filter(['parent' => isset($seite) && $seite instanceof \App\Models\WikiSeite && ! $seite->istStartseite() ? $seite->id : null])) }}" class="wiki-btn wiki-btn-primary" data-tour="wiki-neu">
                    <i class="fas fa-plus"></i><span class="hidden sm:inline">Neue Seite</span>
                </a>
            @endif
        </div>
    </div>

    <div class="wiki-raster">
        <aside class="wiki-nav wiki-nicht-drucken" :class="{ 'max-lg:hidden': !navOffen }" data-tour="wiki-navigation">
            <div class="wiki-nav-links">
                <a href="{{ route('wiki') }}" class="wiki-nav-link {{ request()->routeIs('wiki') ? 'is-aktiv' : '' }}"><i class="fas fa-home"></i> Startseite</a>
                <a href="{{ route('wiki.alle') }}" class="wiki-nav-link {{ request()->routeIs('wiki.alle') ? 'is-aktiv' : '' }}"><i class="fas fa-sort-alpha-down"></i> Alle Seiten A–Z</a>
                <a href="{{ route('wiki.aenderungen') }}" class="wiki-nav-link {{ request()->routeIs('wiki.aenderungen') ? 'is-aktiv' : '' }}"><i class="fas fa-history"></i> Letzte Änderungen</a>
                <a href="{{ route('wiki.tags') }}" class="wiki-nav-link {{ request()->routeIs('wiki.tags', 'wiki.tag') ? 'is-aktiv' : '' }}"><i class="fas fa-tags"></i> Tags</a>
                @if($wikiDarfVerwalten)
                    <a href="{{ route('wiki.struktur') }}" class="wiki-nav-link {{ request()->routeIs('wiki.struktur') ? 'is-aktiv' : '' }}"><i class="fas fa-sitemap"></i> Struktur ordnen</a>
                    <a href="{{ route('wiki.papierkorb') }}" class="wiki-nav-link {{ request()->routeIs('wiki.papierkorb') ? 'is-aktiv' : '' }}"><i class="fas fa-trash-alt"></i> Papierkorb</a>
                @endif
            </div>
            <div class="wiki-nav-kopf"><span class="wiki-nav-titel">Seiten</span></div>
            <nav class="wiki-baum" aria-label="Seitenbaum">
                @if($wikiBaum->isEmpty())
                    <p class="px-2 py-3 text-sm text-gray-400">Noch keine Seiten.</p>
                @else
                    @include('wiki.partials.baum', ['knoten' => $wikiBaum, 'tiefe' => 0])
                @endif
            </nav>
        </aside>

        <main class="min-w-0">
            @yield('wiki')
        </main>
    </div>

    {{-- Lightbox für Bilder im Seiteninhalt --}}
    <div class="wiki-lightbox" x-show="lightbox" x-cloak x-transition.opacity @click="lightbox = null" @keydown.escape.window="lightbox = null">
        <img :src="lightbox" alt="">
    </div>

    <div class="wiki-toast" x-show="meldung" x-cloak x-transition x-text="meldung" role="status"></div>
</div>

@include('wiki.partials.tour')
@endsection
