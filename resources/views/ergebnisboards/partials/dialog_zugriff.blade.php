{{-- Zugriff-Dialog (nur mit Verwaltungsrecht) --}}
<div class="erg-backdrop" x-show.important="dialog === 'zugriff'" x-cloak @click.self="dialogSchliessen()">
    <div class="erg-dialog" data-dialog="zugriff" role="dialog" aria-modal="true" aria-labelledby="erg-zugriff-titel"
         @keydown.tab="fokusFalle($event)">
        <div class="erg-dialog-kopf">
            <div>
                <h2 id="erg-zugriff-titel" class="erg-dialog-titel">Zugriff</h2>
                <p class="text-xs text-slate-500">Wer darf das Board sehen und bearbeiten?</p>
            </div>
            <button type="button" class="erg-icon-btn" @click="dialogSchliessen()" aria-label="Dialog schließen"><i class="fas fa-times"></i></button>
        </div>
        <div class="erg-dialog-body" style="overflow: visible">
            <div class="erg-alert erg-alert-red" x-show.important="zugriffFehler" role="alert">
                <i class="fas fa-exclamation-circle mt-0.5"></i><span x-text="zugriffFehler"></span>
            </div>
            @include('ergebnisboards.partials.zugriff_picker', ['optionen' => $optionen, 'auswahl' => $auswahl, 'formular' => false])
            <template x-if="board.meeting">
                <label class="erg-check">
                    <input type="checkbox" x-model="meetingLesen">
                    <span>Alle Teilnehmenden der Sitzung „<span x-text="board.meeting.titel"></span>“ dürfen lesen</span>
                </label>
            </template>
        </div>
        <div class="erg-dialog-fuss">
            <span></span>
            <div class="flex gap-2">
                <button type="button" class="erg-btn erg-btn-ghost" @click="dialogSchliessen()">Abbrechen</button>
                <button type="button" class="erg-btn erg-btn-primary" @click="zugriffeSpeichern()" :disabled="laedt">
                    <i class="fas fa-save"></i> Speichern
                </button>
            </div>
        </div>
    </div>
</div>
