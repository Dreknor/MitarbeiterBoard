{{--
    Kommentarbereich (Karte im Dialog oder Board in der Seitenleiste).
    $ziel = 'karte' | 'board' – Zustand in kom[$ziel], Logik in ergebnisboards.js (Abschnitt Kommentare).
--}}
<div class="flex flex-col gap-3 min-h-0">
    <div class="erg-alert erg-alert-red" x-show.important="kom.{{ $ziel }}.fehler" role="alert">
        <i class="fas fa-exclamation-circle mt-0.5"></i><span x-text="kom.{{ $ziel }}.fehler"></span>
    </div>

    <div class="erg-kommentare" data-kommentare="{{ $ziel }}" aria-live="polite">
        <p class="text-xs text-slate-400 flex items-center gap-2" x-show.important="kom.{{ $ziel }}.laedt && !kom.{{ $ziel }}.geladen">
            <span class="erg-spinner"></span> Kommentare werden geladen …
        </p>
        <p class="text-sm text-slate-400" x-show.important="kom.{{ $ziel }}.geladen && !kom.{{ $ziel }}.liste.length">
            Noch keine Kommentare.
        </p>
        <template x-for="k in kom.{{ $ziel }}.liste" :key="k.id">
            <div class="erg-kommentar" :class="{ 'is-eigener': k.eigener, 'is-geloescht': k.geloescht }" :id="'kommentar-' + k.id">
                <span class="erg-avatar shrink-0" x-text="k.person.kuerzel" :title="k.person.name"></span>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="text-sm font-semibold text-slate-800" x-text="k.person.name"></span>
                        <span class="text-xs text-slate-400" x-text="zeit(k.erstellt)"></span>
                        <span class="text-xs text-slate-400 italic" x-show.important="k.bearbeitet && !k.geloescht"
                              :title="'bearbeitet ' + zeit(k.bearbeitet)">bearbeitet</span>
                        <span class="ml-auto inline-flex gap-0.5" x-show.important="kom.{{ $ziel }}.bearbeiteId !== k.id && (k.darf_bearbeiten || k.darf_loeschen)">
                            <button type="button" class="erg-icon-btn !w-7 !h-7" x-show.important="k.darf_bearbeiten"
                                    @click="kommentarBearbeiten('{{ $ziel }}', k)" aria-label="Kommentar bearbeiten" title="Bearbeiten">
                                <i class="fas fa-pen text-xs"></i>
                            </button>
                            <button type="button" class="erg-icon-btn !w-7 !h-7" x-show.important="k.darf_loeschen"
                                    @click="kommentarLoeschen('{{ $ziel }}', k)" aria-label="Kommentar löschen" title="Löschen">
                                <i class="far fa-trash-alt text-xs"></i>
                            </button>
                        </span>
                    </div>

                    <p class="erg-kommentar-text is-geloescht" x-show.important="k.geloescht">Kommentar gelöscht</p>
                    <div class="erg-kommentar-text" x-show.important="!k.geloescht && kom.{{ $ziel }}.bearbeiteId !== k.id" x-html="k.html"></div>

                    <template x-if="kom.{{ $ziel }}.bearbeiteId === k.id">
                        <div class="flex flex-col gap-2 mt-1 relative">
                            <textarea class="erg-textarea" rows="3" :data-kommentar-bearbeiten="k.id"
                                      x-model="kom.{{ $ziel }}.bearbeiteText" maxlength="5000" aria-label="Kommentar bearbeiten"
                                      @input="erwaehnungPruefen($event, '{{ $ziel }}', 'bearbeiteText')"
                                      @keydown="kommentarTaste($event, '{{ $ziel }}', 'bearbeiteText')"></textarea>
                            @include('ergebnisboards.partials.erwaehnung_vorschlaege', ['ziel' => $ziel, 'feld' => 'bearbeiteText'])
                            <div class="flex gap-2 justify-end">
                                <button type="button" class="erg-btn erg-btn-ghost erg-btn-sm" @click="kom.{{ $ziel }}.bearbeiteId = null">Abbrechen</button>
                                <button type="button" class="erg-btn erg-btn-primary erg-btn-sm" @click="kommentarSpeichern('{{ $ziel }}')"
                                        :disabled="laedt || !kom.{{ $ziel }}.bearbeiteText.trim()">
                                    <i class="fas fa-save"></i> Speichern
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <template x-if="board.darf_kommentieren">
        <div class="flex flex-col gap-2 relative">
            <textarea class="erg-textarea" rows="3" data-kommentar-eingabe maxlength="5000"
                      x-model="kom.{{ $ziel }}.text"
                      placeholder="{{ $ziel === 'karte' ? 'Kommentar zur Karte …' : 'Kommentar zum Board …' }} (@ erwähnt Kolleg:innen)"
                      aria-label="Neuer Kommentar"
                      @input="erwaehnungPruefen($event, '{{ $ziel }}', 'text')"
                      @keydown="kommentarTaste($event, '{{ $ziel }}', 'text')"></textarea>
            @include('ergebnisboards.partials.erwaehnung_vorschlaege', ['ziel' => $ziel, 'feld' => 'text'])
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-slate-400">Strg + Enter sendet</span>
                <button type="button" class="erg-btn erg-btn-primary erg-btn-sm" @click="kommentarSenden('{{ $ziel }}')"
                        :disabled="laedt || !kom.{{ $ziel }}.text.trim()">
                    <i class="far fa-paper-plane"></i> Kommentieren
                </button>
            </div>
        </div>
    </template>
    <p class="erg-hint mt-0" x-show.important="!board.darf_kommentieren && board.archiviert">
        <i class="fas fa-archive"></i> Archivierte Boards können nicht mehr kommentiert werden.
    </p>
</div>
