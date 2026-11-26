@extends('wiki.layout')

@php
    $darfBearbeiten = auth()->user()->can('update', $seite);
    $darfVerwalten = auth()->user()->can('manage', $seite);
@endphp

@section('wiki')
<div class="wiki-seite-raster {{ count($toc) >= 2 ? 'mit-toc' : '' }}" x-data="wikiSeite({{ $favorit ? 'true' : 'false' }}, '{{ route('wiki.favorit', $seite) }}')">
    <article class="min-w-0 flex flex-col gap-5">
        @if($seite->trashed())
            <div class="wiki-alert wiki-alert-rot">
                <i class="fas fa-trash-alt mt-0.5"></i>
                <div class="flex-1">Diese Seite liegt im Papierkorb und ist nur für die Wiki-Verwaltung sichtbar.</div>
                <form method="POST" action="{{ route('wiki.papierkorb.wiederherstellen', $seite->id) }}">@csrf
                    <button class="wiki-btn wiki-btn-secondary wiki-btn-sm"><i class="fas fa-undo"></i> Wiederherstellen</button>
                </form>
            </div>
        @endif

        <header class="flex flex-col gap-2">
            @if($pfad->isNotEmpty())
                <nav class="wiki-brotkrumen" aria-label="Pfad">
                    <a href="{{ route('wiki') }}"><i class="fas fa-home text-xs text-gray-400"></i></a>
                    @foreach($pfad as $p)
                        <i class="fas fa-chevron-right"></i>
                        <a href="{{ $p->url() }}">{{ $p->titel }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 class="wiki-titel">{{ $seite->titel }}</h1>
                <div class="wiki-aktionen">
                    <button type="button" class="wiki-btn wiki-btn-secondary wiki-btn-icon" @click="favoritUmschalten()" :title="favorit ? 'Aus den Favoriten entfernen' : 'Zu den Favoriten'" :aria-pressed="favorit">
                        <i class="fa-star" :class="favorit ? 'fas text-amber-400' : 'far'"></i>
                    </button>
                    @if($darfBearbeiten)
                        <a href="{{ route('wiki.edit', $seite->slug) }}" class="wiki-btn wiki-btn-primary"><i class="fas fa-pen"></i> Bearbeiten</a>
                    @endif
                    <div class="relative" x-data="{ menue: false }" @click.outside="menue = false">
                        <button type="button" class="wiki-btn wiki-btn-secondary wiki-btn-icon" @click="menue = !menue" aria-label="Weitere Aktionen"><i class="fas fa-ellipsis-h"></i></button>
                        <div x-show="menue" x-cloak class="absolute right-0 z-40 mt-1.5 w-60 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl">
                            <a href="{{ route('wiki.verlauf', $seite->slug) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-history w-4 text-gray-400"></i> Verlauf ({{ $revisionen }})</a>
                            <a href="{{ route('wiki.pdf', $seite->slug) }}" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="far fa-file-pdf w-4 text-gray-400"></i> Als PDF</a>
                            <button type="button" onclick="window.print()" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-print w-4 text-gray-400"></i> Drucken</button>
                            <button type="button" @click="linkKopieren(window.location.href.split('#')[0]); menue = false" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-link w-4 text-gray-400"></i> Link kopieren</button>
                            @if($darfBearbeiten)
                                <a href="{{ route('wiki.create', ['parent' => $seite->id]) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-plus w-4 text-gray-400"></i> Unterseite anlegen</a>
                            @endif
                            @if($darfVerwalten)
                                <div class="my-1 border-t border-gray-100"></div>
                                <a href="{{ route('wiki.einstellungen', $seite) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-cog w-4 text-gray-400"></i> Schutz &amp; Sichtbarkeit</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="wiki-meta">
                <span>Zuletzt geändert {{ $seite->bearbeitet_at?->format('d.m.Y') }} von {{ $seite->geaendert->name }}</span>
                @if($seite->istGeschuetzt())
                    <span class="wiki-badge wiki-badge-grau" title="Nur die Wiki-Verwaltung kann diese Seite bearbeiten"><i class="fas fa-shield-alt"></i> {{ $seite->systemseite ? 'Systemseite' : 'geschützt' }}</span>
                @endif
                @if($einschraenkend)
                    <span class="wiki-badge wiki-badge-gelb" title="Sichtbar nur für: {{ $freigaben->implode(', ') ?: 'Wiki-Verwaltung' }}">
                        <i class="fas fa-lock"></i> eingeschränkt{{ $einschraenkend->id !== $seite->id ? ' (über „'.$einschraenkend->titel.'“)' : '' }}
                    </span>
                @endif
                @foreach($seite->tags as $tag)
                    <a href="{{ route('wiki.tag', $tag) }}" class="wiki-tag"><i class="fas fa-tag text-[10px] opacity-60"></i>{{ $tag->name }}</a>
                @endforeach
            </div>
        </header>

        @if(count($toc) >= 2)
            <details class="wiki-toc-mobil wiki-karte px-4 py-3 xl:hidden wiki-nicht-drucken">
                <summary><i class="fas fa-list-ul mr-1.5 text-gray-400"></i> Inhalt</summary>
                <nav class="mt-2 flex flex-col">
                    @foreach($toc as $eintrag)
                        <a href="#{{ $eintrag['id'] }}" class="py-1 text-sm text-gray-600 {{ $eintrag['ebene'] === 3 ? 'pl-4' : '' }}">{{ $eintrag['text'] }}</a>
                    @endforeach
                </nav>
            </details>
        @endif

        <div class="wiki-karte">
            <div class="wiki-karte-inhalt">
                @if(trim($inhalt) === '')
                    <div class="wiki-leer"><i class="far fa-file"></i>Diese Seite hat noch keinen Inhalt.
                        @if($darfBearbeiten) <a href="{{ route('wiki.edit', $seite->slug) }}" class="font-semibold text-blue-700">Jetzt schreiben</a>@endif
                    </div>
                @else
                    <div class="wiki-inhalt" x-ref="inhalt" @click="inhaltKlick($event)">{!! $inhalt !!}</div>
                @endif
            </div>
        </div>

        @if($dateien->isNotEmpty())
            <section class="wiki-karte">
                <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-paperclip"></i> Dateien</h2></div>
                <div class="wiki-liste">
                    @foreach($dateien as $datei)
                        @php $istBild = str_starts_with((string) $datei->mime_type, 'image/'); @endphp
                        <div class="wiki-datei-zeile">
                            <a href="{{ route('wiki.anhang', $datei) }}" target="_blank" class="wiki-datei-icon" @if($istBild) @click.prevent="lightbox = '{{ route('wiki.anhang', $datei) }}'" @endif>
                                @if($istBild)
                                    <img src="{{ route('wiki.anhang', $datei) }}" alt="" loading="lazy">
                                @else
                                    <i class="far {{ str_contains((string) $datei->mime_type, 'pdf') ? 'fa-file-pdf' : 'fa-file' }}"></i>
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('wiki.anhang', $datei) }}" target="_blank" class="block truncate text-sm font-semibold text-gray-800 hover:text-blue-700">{{ $datei->file_name }}</a>
                                <div class="text-xs text-gray-400">{{ $datei->human_readable_size }} · {{ $datei->created_at?->format('d.m.Y') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if($kinder->isNotEmpty() || $backlinks->isNotEmpty())
            <div class="grid gap-5 md:grid-cols-2 wiki-nicht-drucken">
                @if($kinder->isNotEmpty())
                    <section class="wiki-karte">
                        <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-sitemap"></i> Unterseiten</h2></div>
                        <div class="wiki-liste">
                            @foreach($kinder as $k)
                                <a href="{{ $k->url() }}" class="wiki-zeile text-sm font-medium text-gray-800"><i class="far fa-file-alt text-gray-400"></i> {{ $k->titel }}</a>
                            @endforeach
                        </div>
                    </section>
                @endif
                @if($backlinks->isNotEmpty())
                    <section class="wiki-karte">
                        <div class="wiki-karte-kopf"><h2 class="wiki-karte-titel"><i class="fas fa-link"></i> Verlinkt von</h2></div>
                        <div class="wiki-liste">
                            @foreach($backlinks as $b)
                                <a href="{{ $b->url() }}" class="wiki-zeile text-sm font-medium text-gray-800"><i class="fas fa-reply text-gray-400"></i> {{ $b->titel }}</a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        @endif
    </article>

    @if(count($toc) >= 2)
        <aside class="hidden xl:block wiki-nicht-drucken">
            <nav class="wiki-toc" aria-label="Inhaltsverzeichnis" x-ref="toc">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Auf dieser Seite</div>
                @foreach($toc as $eintrag)
                    <a href="#{{ $eintrag['id'] }}" data-toc="{{ $eintrag['id'] }}" class="ebene-{{ $eintrag['ebene'] }}" :class="{ 'is-aktiv': abschnitt === '{{ $eintrag['id'] }}' }">{{ $eintrag['text'] }}</a>
                @endforeach
            </nav>
        </aside>
    @endif
</div>
@endsection
