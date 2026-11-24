{{-- PNG-Vorschau mit Speichern / Zwischenablage / neuer Tab --}}
<div class="erg-backdrop" x-show.important="dialog === 'export'" x-cloak @click.self="dialogSchliessen()">
    <div class="erg-dialog is-breit" data-dialog="export" role="dialog" aria-modal="true" aria-labelledby="erg-export-titel"
         @keydown.tab="fokusFalle($event)">
        <div class="erg-dialog-kopf">
            <div>
                <h2 id="erg-export-titel" class="erg-dialog-titel">PNG-Grafik erstellt</h2>
                <p class="text-xs text-slate-500">Speichern, in die Zwischenablage kopieren oder per Rechtsklick → „Bild speichern unter …“.</p>
            </div>
            <button type="button" class="erg-icon-btn" @click="dialogSchliessen()" aria-label="Dialog schließen"><i class="fas fa-times"></i></button>
        </div>
        <div class="erg-dialog-body bg-slate-100 items-center">
            <template x-if="exportUrl">
                <img :src="exportUrl" :alt="'Vorschau ' + board.titel" class="max-w-full rounded-xl shadow-md border border-slate-300 bg-white" style="max-height: 60vh">
            </template>
        </div>
        <div class="erg-dialog-fuss">
            <span class="text-xs text-slate-500" x-text="'Auflösung: ' + exportBreite + ' px Breite'"></span>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" @click="exportKopieren()"><i class="far fa-copy"></i> In Zwischenablage</button>
                <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" @click="exportNeuerTab()"><i class="fas fa-external-link-alt"></i> Neuer Tab</button>
                <a :href="exportUrl" :download="exportDateiname" class="erg-btn erg-btn-primary erg-btn-sm" data-autofocus><i class="fas fa-download"></i> PNG speichern</a>
            </div>
        </div>
    </div>
</div>
