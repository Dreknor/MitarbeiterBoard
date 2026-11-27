{{--
    Das Board selbst (wird auch als PNG exportiert und gedruckt).
    Ein generisches Raster für alle Vorlagen: grid-template-columns = board.spalten,
    jedes Hauptfeld über spalte/spalten/zeile/zeilen, Unterbereiche (eltern) im Feld.
    Alles mit .erg-no-export fehlt im PNG/Druck.
--}}
<div id="erg-board" x-ref="board" class="erg-board"
     :class="['erg-schema-' + board.farbschema, { 'is-bearbeiten': schreibbar, 'is-frei': board.bereiche_frei }]"
     :style="'min-width:' + boardMinBreite + 'px'">

    {{-- Kopf --}}
    <header class="erg-kopf">
        <div class="min-w-0 flex-1">
            <div class="erg-kopf-badges">
                <span x-text="board.vorlage_titel"></span>
                <template x-if="board.meeting">
                    <span class="inline-flex items-center gap-2"><span aria-hidden="true">•</span><span x-text="board.meeting.titel"></span></span>
                </template>
                <template x-if="board.archiviert">
                    <span class="inline-flex items-center gap-2"><span aria-hidden="true">•</span><span>Archiv</span></span>
                </template>
            </div>
            <h1 class="erg-titel" x-text="board.titel" spellcheck="false"
                :contenteditable="schreibbar ? 'true' : 'false'" data-placeholder="Titel"
                :title="schreibbar ? 'Klicken zum Bearbeiten' : null"
                @keydown.enter="enterBlur($event)" @blur="kopfSpeichern('titel', $el)"></h1>
            <p class="erg-untertitel" x-text="board.untertitel" spellcheck="false"
               x-show.important="board.untertitel || schreibbar"
               :contenteditable="schreibbar ? 'true' : 'false'" data-placeholder="Untertitel ergänzen …"
               @keydown.enter="enterBlur($event)" @blur="kopfSpeichern('untertitel', $el)"></p>
        </div>
        <div class="erg-stand" x-show.important="board.kopfzeile || schreibbar">
            <div class="erg-stand-label">Stand / Gremium</div>
            <div class="erg-stand-wert" x-text="board.kopfzeile" spellcheck="false"
                 :contenteditable="schreibbar ? 'true' : 'false'" data-placeholder="z. B. Gesamtkollegium • 2026/27"
                 @keydown.enter="enterBlur($event)" @blur="kopfSpeichern('kopfzeile', $el)"></div>
        </div>
    </header>

    {{-- Achse links + Raster --}}
    <div :class="board.achsen && board.achsen.y ? 'erg-mit-achse' : ''">
        <template x-if="board.achsen && board.achsen.y">
            <div class="erg-achse erg-achse-y" aria-label="Achse">
                <div class="flex flex-col items-center gap-1">
                    <i class="fas fa-arrow-up text-slate-600 text-xs"></i>
                    <span class="erg-vertikal text-[10px] font-bold uppercase tracking-wider py-1" x-text="board.achsen.y.oben"
                          :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="oben"
                          @keydown.enter="enterBlur($event)" @blur="achseSpeichern('y', 'oben', $el)"></span>
                </div>
                <div class="flex flex-col items-center gap-3 my-auto">
                    <span class="erg-vertikal text-xs font-extrabold tracking-[0.2em] uppercase text-rose-700/80" x-show.important="board.achsen.y.zusatz || schreibbar"
                          x-text="board.achsen.y.zusatz" :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="Zusatz"
                          @keydown.enter="enterBlur($event)" @blur="achseSpeichern('y', 'zusatz', $el)"></span>
                    <span class="w-px h-8 bg-slate-300" x-show.important="board.achsen.y.zusatz || schreibbar"></span>
                    <span class="erg-vertikal text-xs font-extrabold tracking-[0.2em] uppercase text-slate-700" x-text="board.achsen.y.titel"
                          :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="Achse"
                          @keydown.enter="enterBlur($event)" @blur="achseSpeichern('y', 'titel', $el)"></span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <span class="erg-vertikal text-[10px] font-bold uppercase tracking-wider py-1" x-text="board.achsen.y.unten"
                          :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="unten"
                          @keydown.enter="enterBlur($event)" @blur="achseSpeichern('y', 'unten', $el)"></span>
                    <i class="fas fa-arrow-down text-slate-600 text-xs"></i>
                </div>
            </div>
        </template>

        <div class="erg-raster" :class="{ 'is-frei': board.bereiche_frei }" :style="'grid-template-columns:' + board.spalten">
            <template x-for="feld in hauptbereiche" :key="feld.schluessel">
                <section class="erg-feld" :style="feldStil(feld)" :aria-label="feld.titel"
                         :class="['erg-farbe-' + feld.farbe, { 'is-mittig': !board.bereiche_frei && feld.zeilen > 1 && !(seitenfeld && seitenfeld.schluessel === feld.schluessel) }]">
                    <div class="erg-feld-kopf">
                        <div class="min-w-0">
                            <span class="erg-feld-badge" x-show.important="feld.badge" x-text="feld.badge"></span>
                            <h2 class="erg-feld-titel" x-text="feld.titel"></h2>
                            <p class="erg-feld-hinweis" x-show.important="feld.hinweis" x-text="feld.hinweis"></p>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <template x-if="feld.icon">
                                <div class="erg-feld-icon" aria-hidden="true"><i :class="feld.icon"></i></div>
                            </template>
                            <span class="erg-feld-zahl" x-show.important="board.bereiche_frei" x-text="anzahl(feld.schluessel)"
                                  :aria-label="anzahl(feld.schluessel) + ' Karten'"></span>
                        </div>
                    </div>

                    @include('ergebnisboards.partials.karten', ['f' => 'feld'])

                    <template x-for="unter in unterbereiche(feld.schluessel)" :key="unter.schluessel">
                        <div class="erg-unterbereich" :class="'erg-farbe-' + unter.farbe" :aria-label="unter.titel">
                            <div>
                                <span class="erg-unterbereich-titel"><span class="erg-punkt"></span><span x-text="unter.titel"></span></span>
                                <span class="erg-feld-badge mt-1" x-show.important="unter.badge" x-text="unter.badge"></span>
                                <p class="erg-feld-hinweis mt-0.5" x-show.important="unter.hinweis" x-text="unter.hinweis"></p>
                            </div>
                            @include('ergebnisboards.partials.karten', ['f' => 'unter'])
                        </div>
                    </template>

                    <template x-if="legendeImSeitenfeld && seitenfeld.schluessel === feld.schluessel">
                        <div class="mt-auto">
                            @include('ergebnisboards.partials.legende', ['senkrecht' => true])
                        </div>
                    </template>
                </section>
            </template>
        </div>
    </div>

    {{-- Achse unten (Ertrag) + Fußzeile --}}
    <template x-if="board.achsen && board.achsen.x">
        <div :class="board.achsen.y ? 'erg-mit-achse' : ''">
            <div x-show.important="board.achsen.y"></div>
            <div class="erg-raster items-center" :style="'grid-template-columns:' + board.spalten">
                <div class="erg-achse erg-achse-x" :style="'grid-column:' + achseXSpalten">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500" x-text="board.achsen.x.links"
                          :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="links"
                          @keydown.enter="enterBlur($event)" @blur="achseSpeichern('x', 'links', $el)"></span>
                    <div class="flex items-center gap-3 flex-1 mx-6">
                        <span class="text-xs font-extrabold uppercase tracking-[0.2em] text-slate-700" x-text="board.achsen.x.titel"
                              :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="Achse"
                              @keydown.enter="enterBlur($event)" @blur="achseSpeichern('x', 'titel', $el)"></span>
                        <div class="erg-achse-linie"></div>
                        <span class="text-xs font-extrabold uppercase tracking-[0.2em] text-emerald-800" x-text="board.achsen.x.ziel"
                              x-show.important="board.achsen.x.ziel || schreibbar"
                              :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="Ziel"
                              @keydown.enter="enterBlur($event)" @blur="achseSpeichern('x', 'ziel', $el)"></span>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700" x-text="board.achsen.x.rechts"
                          :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="rechts"
                          @keydown.enter="enterBlur($event)" @blur="achseSpeichern('x', 'rechts', $el)"></span>
                </div>
                <template x-if="seitenfeld">
                    <div class="erg-fusszeile" :style="'grid-column:' + spaltenAnzahl" x-text="board.fusszeile"
                         x-show.important="board.fusszeile || schreibbar"
                         :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="Fußzeile"
                         @keydown.enter="enterBlur($event)" @blur="kopfSpeichern('fusszeile', $el)"></div>
                </template>
            </div>
        </div>
    </template>

    {{-- Legende unter dem Raster + Fußzeile (wenn nicht in Seitenspalte/Achsenzeile) --}}
    <template x-if="(board.legende && !legendeImSeitenfeld) || ((board.fusszeile || schreibbar) && !(board.achsen && board.achsen.x && seitenfeld))">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <template x-if="board.legende && !legendeImSeitenfeld">
                    <div>@include('ergebnisboards.partials.legende', ['senkrecht' => false])</div>
                </template>
            </div>
            <template x-if="!(board.achsen && board.achsen.x && seitenfeld)">
                <div class="erg-fusszeile" x-text="board.fusszeile" x-show.important="board.fusszeile || schreibbar"
                     :contenteditable="schreibbar ? 'true' : 'false'" spellcheck="false" data-placeholder="Fußzeile ergänzen …"
                     @keydown.enter="enterBlur($event)" @blur="kopfSpeichern('fusszeile', $el)"></div>
            </template>
        </div>
    </template>
</div>
