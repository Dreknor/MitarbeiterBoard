@extends('layouts.app')

@push('css')
    @vite('resources/css/meetings.css')
@endpush

@section('content')
<div class="meeting-wrapper" x-data="globalSearch()" x-cloak>

    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-6">
            <h1 class="mtg-page-title text-2xl font-bold text-gray-900">Globale Suche</h1>
            <p class="text-sm text-gray-500 mt-1">Durchsucht Nachrichten, Themen deiner Gruppen, Meetings sowie Themen und Protokolle aus Meetings, an denen du teilnimmst.</p>
        </div>

        <div class="relative mb-6">
            <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" class="mtg-search-input" x-model="query" x-ref="input"
                   @input.debounce.350ms="run()" @keydown.enter.prevent="run()"
                   placeholder="Suchbegriff eingeben (mind. 3 Zeichen) …" autofocus autocomplete="off"
                   aria-label="Suchbegriff">
            <i class="fas fa-circle-notch fa-spin absolute right-5 top-1/2 -translate-y-1/2 text-blue-500" x-show="loading" style="display:none;"></i>
        </div>

        {{-- Ergebnis-Navigation --}}
        <nav class="mtg-pills mb-4" x-show="sections.length > 1" style="display:none;" aria-label="Ergebnisbereiche">
            <template x-for="s in sections" :key="s.key">
                <a :href="'#' + s.key" class="mtg-pill">
                    <i :class="s.icon"></i>
                    <span x-text="s.title"></span>
                    <span class="text-gray-400" x-text="'(' + s.items.length + ')'"></span>
                </a>
            </template>
        </nav>

        {{-- Hinweise --}}
        <div class="mtg-card p-8 text-center text-sm text-gray-500" x-show="state === 'idle'">
            <i class="fas fa-search text-3xl text-gray-300 mb-3 block"></i>
            Tipp: Auch Protokolle aus freien Meetings werden durchsucht – sofern du daran teilnimmst.
        </div>
        <div class="mtg-card p-8 text-center text-sm text-gray-500" x-show="state === 'empty'" style="display:none;">
            <i class="far fa-folder-open text-3xl text-gray-300 mb-3 block"></i>
            Keine Ergebnisse für „<span x-text="lastQuery"></span>“.
        </div>
        <div class="mtg-alert mtg-alert-danger" x-show="state === 'error'" style="display:none;">
            Die Suche ist fehlgeschlagen. Bitte später erneut versuchen.
        </div>

        {{-- Ergebnisse --}}
        <template x-for="s in sections" :key="s.key">
            <section class="mtg-card mb-5" :id="s.key">
                <div class="mtg-card-head">
                    <h2 class="mtg-card-title"><i :class="s.icon"></i> <span x-text="s.title"></span></h2>
                    <span class="mtg-badge mtg-badge-gray" x-text="s.items.length + ' Treffer'"></span>
                </div>
                <ul>
                    <template x-for="item in s.items" :key="s.key + '-' + item.id">
                        <li>
                            <div class="mtg-result">
                                <span class="text-xs text-gray-400 w-20 shrink-0 pt-0.5 tabular-nums" x-text="item.date || ''"></span>
                                <span class="flex-1 min-w-0">
                                    <a x-show="item.url" :href="item.url" class="block font-semibold text-gray-900 hover:text-blue-700" x-html="highlight(item.title)"></a>
                                    <span x-show="!item.url" class="block font-semibold text-gray-900" x-html="highlight(item.title)"></span>
                                    <span class="block text-xs text-violet-700 mt-0.5" x-show="item.meta" x-text="item.meta"></span>
                                    <span class="block text-sm text-gray-500 mt-1" x-show="item.snippet" x-html="highlight(item.snippet)"></span>
                                </span>
                                <i class="fas fa-chevron-right text-gray-300 pt-1" x-show="item.url"></i>
                            </div>
                        </li>
                    </template>
                </ul>
            </section>
        </template>
    </div>
</div>
@endsection

@push('js')
    <script>
        window.globalSearch = function () {
            const escapeHtml = (s) => (s || '').toString()
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            const escapeRegex = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            return {
                query: '',
                lastQuery: '',
                loading: false,
                state: 'idle',
                sections: [],
                controller: null,

                async run() {
                    const text = this.query.trim();
                    if (text.length < 3) {
                        this.sections = [];
                        this.state = 'idle';
                        return;
                    }
                    if (this.controller) this.controller.abort();
                    this.controller = new AbortController();
                    this.loading = true;

                    try {
                        const response = await fetch('{{ url('search/search') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ text }),
                            signal: this.controller.signal,
                        });
                        if (!response.ok) throw new Error(response.status);
                        const data = await response.json();
                        this.sections = data.sections || [];
                        this.lastQuery = text;
                        this.state = this.sections.length ? 'results' : 'empty';
                    } catch (e) {
                        if (e.name === 'AbortError') return;
                        this.sections = [];
                        this.state = 'error';
                    } finally {
                        this.loading = false;
                    }
                },

                highlight(value) {
                    const safe = escapeHtml(value);
                    const words = this.lastQuery.split(/\s+/).filter(w => w.length >= 2).map(escapeRegex);
                    if (!words.length) return safe;
                    return safe.replace(new RegExp('(' + words.map(escapeHtml).join('|') + ')', 'gi'), '<mark>$1</mark>');
                },
            };
        };
    </script>
@endpush
