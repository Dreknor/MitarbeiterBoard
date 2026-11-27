{{-- Felder-Dialog: Bereiche umbenennen, einfärben, Unterbereiche; bei freien Boards auch hinzufügen/entfernen/umsortieren --}}
<div class="erg-backdrop" x-show.important="dialog === 'felder'" x-cloak @click.self="dialogSchliessen()">
    <div class="erg-dialog is-breit" data-dialog="felder" role="dialog" aria-modal="true" aria-labelledby="erg-felder-titel"
         @keydown.tab="fokusFalle($event)">
        <div class="erg-dialog-kopf">
            <div>
                <h2 id="erg-felder-titel" class="erg-dialog-titel">Felder bearbeiten</h2>
                <p class="text-xs text-slate-500"
                   x-text="board.bereiche_frei ? 'Spalten können hinzugefügt, entfernt und umsortiert werden.' : 'Die Felder dieser Vorlage stehen fest – Texte, Farben und Unterbereiche lassen sich anpassen.'"></p>
            </div>
            <button type="button" class="erg-icon-btn" @click="dialogSchliessen()" aria-label="Dialog schließen"><i class="fas fa-times"></i></button>
        </div>

        <div class="erg-dialog-body">
            <div class="erg-alert erg-alert-red" x-show.important="felderFehler" role="alert">
                <i class="fas fa-exclamation-circle mt-0.5"></i><span x-text="felderFehler"></span>
            </div>

            <template x-for="(feld, i) in felder" :key="feld.schluessel || ('neu' + i)">
                <div class="flex flex-col gap-2">
                    @include('ergebnisboards.partials.feld_zeile', ['f' => 'feld', 'liste' => 'felder', 'index' => 'i', 'unter' => false])
                    <template x-for="(u, j) in feld.unter" :key="u.schluessel || ('neu' + i + '-' + j)">
                        @include('ergebnisboards.partials.feld_zeile', ['f' => 'u', 'liste' => 'feld.unter', 'index' => 'j', 'unter' => true])
                    </template>
                    <div class="ml-6">
                        <button type="button" class="erg-btn erg-btn-ghost erg-btn-sm" @click="unterNeu(feld)" :disabled="feld.neu"
                                :title="feld.neu ? 'Erst speichern, dann Unterbereiche anlegen' : null">
                            <i class="fas fa-level-up-alt fa-rotate-90"></i> Unterbereich in „<span x-text="feld.titel"></span>“
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="board.bereiche_frei">
                <button type="button" class="erg-btn erg-btn-secondary self-start" @click="feldNeu()">
                    <i class="fas fa-plus"></i> Feld hinzufügen
                </button>
            </template>
        </div>

        <div class="erg-dialog-fuss">
            <p class="text-xs text-slate-500">Felder mit Karten können nicht entfernt werden.</p>
            <div class="flex gap-2">
                <button type="button" class="erg-btn erg-btn-ghost" @click="dialogSchliessen()">Abbrechen</button>
                <button type="button" class="erg-btn erg-btn-primary" @click="felderSpeichern()" :disabled="laedt">
                    <i class="fas fa-save"></i> Speichern
                </button>
            </div>
        </div>
    </div>
</div>
