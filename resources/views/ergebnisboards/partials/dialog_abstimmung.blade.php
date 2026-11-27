{{-- Abstimmung („Punkte kleben“): Starten/Beenden/Zurücksetzen mit Schreibrecht, sonst nur Stand --}}
<div class="erg-backdrop" x-show.important="dialog === 'abstimmung'" x-cloak @click.self="dialogSchliessen()">
    <div class="erg-dialog" data-dialog="abstimmung" role="dialog" aria-modal="true" aria-labelledby="erg-abst-titel"
         @keydown.tab="fokusFalle($event)">
        <div class="erg-dialog-kopf">
            <div>
                <h2 id="erg-abst-titel" class="erg-dialog-titel">Abstimmung – Punkte kleben</h2>
                <p class="text-xs text-slate-500">Alle mit Zugriff verteilen ihre Punkte auf die Karten, die ihnen am wichtigsten sind.</p>
            </div>
            <button type="button" class="erg-icon-btn" @click="dialogSchliessen()" aria-label="Dialog schließen"><i class="fas fa-times"></i></button>
        </div>

        <div class="erg-dialog-body">
            <div class="erg-alert erg-alert-red" x-show.important="abstimmungFehler" role="alert">
                <i class="fas fa-exclamation-circle mt-0.5"></i><span x-text="abstimmungFehler"></span>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold">Status:</span>
                <span class="erg-chip" :class="{ 'bg-emerald-100 text-emerald-800': abstimmung.status === 'laeuft' }"
                      x-text="{ aus: 'keine Abstimmung', laeuft: 'läuft', beendet: 'beendet' }[abstimmung.status]"></span>
                <span class="text-slate-500" x-show.important="abstimmung.status !== 'aus'"
                      x-text="abstimmung.teilnehmende + (abstimmung.teilnehmende === 1 ? ' Person hat' : ' Personen haben') + ' abgestimmt'"></span>
            </div>

            <template x-if="board.darf_schreiben">
                <div class="flex flex-col gap-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="erg-label" for="erg-abst-punkte">Punkte je Person</label>
                            <input id="erg-abst-punkte" type="number" min="1" max="10" class="erg-input" x-model.number="abstimmungForm.punkte">
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="erg-check">
                                <input type="checkbox" x-model="abstimmungForm.verdeckt">
                                <span>Verdeckt – Ergebnis erst nach dem Beenden zeigen</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <template x-if="abstimmung.status !== 'laeuft'">
                            <button type="button" class="erg-btn erg-btn-primary" :disabled="laedt"
                                    @click="abstimmungSetzen({ status: 'laeuft', punkte: abstimmungForm.punkte, verdeckt: abstimmungForm.verdeckt })">
                                <i class="fas fa-play"></i> <span x-text="abstimmung.status === 'beendet' ? 'Fortsetzen' : 'Abstimmung starten'"></span>
                            </button>
                        </template>
                        <template x-if="abstimmung.status === 'laeuft'">
                            <button type="button" class="erg-btn erg-btn-secondary" :disabled="laedt"
                                    @click="abstimmungSetzen({ punkte: abstimmungForm.punkte, verdeckt: abstimmungForm.verdeckt })">
                                <i class="fas fa-save"></i> Einstellungen übernehmen
                            </button>
                        </template>
                        <template x-if="abstimmung.status === 'laeuft'">
                            <button type="button" class="erg-btn erg-btn-primary" :disabled="laedt" @click="abstimmungSetzen({ status: 'beendet' })">
                                <i class="fas fa-flag-checkered"></i> Beenden &amp; Ergebnis zeigen
                            </button>
                        </template>
                        <template x-if="abstimmung.status !== 'aus'">
                            <button type="button" class="erg-btn erg-btn-ghost" :disabled="laedt" @click="abstimmungSetzen({ status: 'aus' })"
                                    title="Punkte bleiben gespeichert, werden aber nicht mehr angezeigt">
                                <i class="far fa-eye-slash"></i> Ausblenden
                            </button>
                        </template>
                        <template x-if="abstimmung.status !== 'aus'">
                            <button type="button" class="erg-btn erg-btn-danger" :disabled="laedt" @click="abstimmungZuruecksetzen()">
                                <i class="fas fa-undo"></i> Alle Punkte zurücksetzen
                            </button>
                        </template>
                    </div>
                    <p class="erg-hint mt-0">
                        Punkte vergeben dürfen alle, die das Board sehen. Punkte ändern die Karten nicht –
                        „Nach Punkten sortieren“ ordnet nur die Ansicht; verschieben Sie Karten danach bei Bedarf selbst.
                    </p>
                </div>
            </template>
            <template x-if="!board.darf_schreiben">
                <p class="text-sm text-slate-600">Die Abstimmung starten und beenden Personen mit Schreibrecht.</p>
            </template>
        </div>

        <div class="erg-dialog-fuss">
            <span></span>
            <button type="button" class="erg-btn erg-btn-ghost" @click="dialogSchliessen()">Schließen</button>
        </div>
    </div>
</div>
