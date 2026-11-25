{{-- Karten-Dialog: bearbeiten (Bearbeitungsmodus) oder nur lesen --}}
<div class="erg-backdrop" x-show.important="dialog === 'karte'" x-cloak @click.self="dialogSchliessen()">
    <div class="erg-dialog" data-dialog="karte" role="dialog" aria-modal="true" aria-labelledby="erg-karte-titel"
         @keydown.tab="fokusFalle($event)">
        <div class="erg-dialog-kopf">
            <div class="min-w-0">
                <h2 id="erg-karte-titel" class="erg-dialog-titel"
                    x-text="!schreibbar ? form.titel : (form.id ? 'Karte bearbeiten' : 'Neue Karte')"></h2>
                <p class="text-xs text-slate-500" x-text="bereichTitel(form.bereich)"></p>
            </div>
            <button type="button" class="erg-icon-btn" @click="dialogSchliessen()" aria-label="Dialog schließen"><i class="fas fa-times"></i></button>
        </div>

        {{-- Reiter (nur bei gespeicherten Karten) --}}
        <div class="erg-reiter" role="tablist" x-show.important="form.id">
            <button type="button" role="tab" :aria-selected="(kartenReiter === 'details').toString()"
                    :class="{ 'is-active': kartenReiter === 'details' }" @click="reiterWaehlen('details')">
                <i class="far fa-sticky-note"></i> Details
            </button>
            <button type="button" role="tab" :aria-selected="(kartenReiter === 'kommentare').toString()"
                    :class="{ 'is-active': kartenReiter === 'kommentare' }" @click="reiterWaehlen('kommentare')">
                <i class="far fa-comment"></i> Kommentare
                <span class="erg-zaehler" x-show.important="form.kommentare" x-text="form.kommentare"></span>
            </button>
            <button type="button" role="tab" :aria-selected="(kartenReiter === 'verlauf').toString()"
                    :class="{ 'is-active': kartenReiter === 'verlauf' }" @click="reiterWaehlen('verlauf')">
                <i class="fas fa-history"></i> Verlauf
            </button>
        </div>

        <div class="erg-dialog-body" x-show.important="form.id && kartenReiter === 'kommentare'">
            @include('ergebnisboards.partials.kommentare', ['ziel' => 'karte'])
        </div>
        <div class="erg-dialog-body" x-show.important="form.id && kartenReiter === 'verlauf'">
            @include('ergebnisboards.partials.verlauf', ['ziel' => 'karte'])
        </div>

        <div class="erg-dialog-body" x-show.important="kartenReiter === 'details'">
            <div class="erg-alert erg-alert-red" x-show.important="formFehler" role="alert">
                <i class="fas fa-exclamation-circle mt-0.5"></i><span x-text="formFehler"></span>
            </div>

            {{-- Bearbeiten --}}
            <template x-if="schreibbar">
                <div class="flex flex-col gap-4">
                    <div>
                        <label class="erg-label" for="erg-k-titel">Titel <span class="erg-required">*</span></label>
                        <input id="erg-k-titel" type="text" class="erg-input" x-model="form.titel" maxlength="255" data-autofocus
                               @keydown.enter.prevent="karteSpeichern()">
                    </div>
                    <div>
                        <label class="erg-label" for="erg-k-zusatz">Zusatz</label>
                        <textarea id="erg-k-zusatz" class="erg-textarea" rows="2" x-model="form.zusatz" maxlength="2000"></textarea>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="erg-label" for="erg-k-feld">Feld</label>
                            <select id="erg-k-feld" class="erg-select" x-model="form.bereich">
                                <template x-for="o in bereichOptionen" :key="o.wert">
                                    <option :value="o.wert" x-text="o.text" :selected="o.wert === form.bereich"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="erg-label" for="erg-k-etikett">Etikett</label>
                            <input id="erg-k-etikett" type="text" class="erg-input" x-model="form.etikett" maxlength="60" placeholder="z. B. Leitung">
                        </div>
                        <div>
                            <label class="erg-label" for="erg-k-faellig">Fällig am</label>
                            <input id="erg-k-faellig" type="date" class="erg-input" x-model="form.faellig_am">
                        </div>
                        <div class="flex flex-col justify-end gap-2 pb-1">
                            <label class="erg-check"><input type="checkbox" x-model="form.fokus"> <span><span class="text-amber-500">★</span> Arbeitsschwerpunkt (Fokus)</span></label>
                            <label class="erg-check" x-show.important="formRaster2"><input type="checkbox" x-model="form.breit"> <span>Breit (über beide Spalten)</span></label>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="erg-label mb-0">Zuständig</span>
                            <span class="text-xs text-slate-400" x-text="form.zustaendige.length ? form.zustaendige.length + ' ausgewählt' : ''"></span>
                        </div>
                        <div class="flex flex-wrap gap-1 mb-2" x-show.important="form.zustaendige.length">
                            <template x-for="id in form.zustaendige" :key="id">
                                <span class="erg-chip">
                                    <span x-text="personName(id)"></span>
                                    <button type="button" @click="form.zustaendige = form.zustaendige.filter(x => x !== id)" :aria-label="personName(id) + ' entfernen'">&times;</button>
                                </span>
                            </template>
                        </div>
                        <input type="search" class="erg-input erg-input-sm mb-1.5" placeholder="Person suchen …" x-model="personSuche" aria-label="Person suchen">
                        <div class="erg-personen">
                            <template x-for="p in personenGefiltert" :key="p.id">
                                <label>
                                    <input type="checkbox" class="accent-emerald-600" :value="p.id" x-model.number="form.zustaendige">
                                    <span class="erg-avatar" x-text="p.kuerzel"></span>
                                    <span x-text="p.name" class="truncate"></span>
                                </label>
                            </template>
                            <p class="text-xs text-slate-400 p-2" x-show.important="!personenGefiltert.length">Keine Personen gefunden.</p>
                        </div>
                        <p class="erg-hint">Zuständig sein können alle Personen mit Zugriff auf das Board.</p>
                    </div>
                </div>
            </template>

            {{-- Nur lesen --}}
            <template x-if="!schreibbar">
                <div class="flex flex-col gap-3 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="erg-chip" x-show.important="form.etikett" x-text="form.etikett"></span>
                        <span class="erg-chip bg-amber-100 text-amber-900" x-show.important="form.fokus">★ Arbeitsschwerpunkt</span>
                        <span class="erg-chip" :class="form.ueberfaellig ? 'bg-red-100 text-red-700' : ''" x-show.important="form.faellig_am">
                            <i class="far fa-calendar-alt"></i> <span x-text="'fällig ' + datum(form.faellig_am)"></span>
                        </span>
                    </div>
                    <p class="text-slate-700 whitespace-pre-line" x-show.important="form.zusatz" x-text="form.zusatz"></p>
                    <div x-show.important="form.zustaendigeNamen.length">
                        <div class="erg-abschnitt-titel mb-1"><i class="fas fa-users"></i> Zuständig</div>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="z in form.zustaendigeNamen" :key="z.id">
                                <span class="erg-chip"><span class="erg-avatar" x-text="z.kuerzel"></span><span x-text="z.name"></span></span>
                            </template>
                        </div>
                    </div>
                    <template x-if="board.darf_schreiben">
                        <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm self-start" @click="bearbeiten = true">
                            <i class="fas fa-pen"></i> Bearbeiten
                        </button>
                    </template>
                </div>
            </template>

            {{-- Anhänge --}}
            <div class="erg-abschnitt" x-show.important="form.id && (form.anhaenge.length || schreibbar)">
                <div class="erg-abschnitt-titel"><i class="fas fa-paperclip"></i> Anhänge</div>
                <template x-for="a in form.anhaenge" :key="a.id">
                    <div class="erg-datei">
                        <i class="far fa-file text-slate-400"></i>
                        <a :href="a.url" target="_blank" rel="noopener" x-text="a.name"></a>
                        <span class="text-xs text-slate-400 shrink-0" x-text="groesse(a.groesse)"></span>
                        <button type="button" class="erg-icon-btn" x-show.important="schreibbar" @click="dateiLoeschen(a)" :aria-label="'Datei ' + a.name + ' entfernen'">
                            <i class="far fa-trash-alt"></i>
                        </button>
                    </div>
                </template>
                <p class="text-xs text-slate-400" x-show.important="!form.anhaenge.length">Noch keine Dateien.</p>
                <template x-if="schreibbar">
                    <label class="erg-btn erg-btn-secondary erg-btn-sm self-start" :class="{ 'opacity-50 pointer-events-none': hochladen }">
                        <span class="erg-spinner" x-show.important="hochladen"></span>
                        <i class="fas fa-upload" x-show.important="!hochladen"></i>
                        <span x-text="hochladen ? 'Wird hochgeladen …' : 'Dateien hochladen'"></span>
                        <input type="file" multiple class="sr-only" @change="dateienHochladen($event)"
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.zip">
                    </label>
                </template>
                <p class="erg-hint" x-show.important="schreibbar" x-text="'Bis ' + Math.round(maxDateiKb / 1024) + ' MB je Datei (PDF, Bilder, Office, ZIP).'"></p>
            </div>
            <p class="erg-hint" x-show.important="!form.id && schreibbar"><i class="fas fa-paperclip"></i> Dateien können nach dem ersten Speichern angehängt werden.</p>

            {{-- Aufgabe --}}
            <div class="erg-abschnitt" x-show.important="form.id && (form.aufgabe || board.darf_schreiben)">
                <div class="erg-abschnitt-titel"><i class="fas fa-tasks"></i> Aufgabe</div>
                <template x-if="form.aufgabe">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="erg-aufgabe-status" :class="{ 'is-erledigt': form.aufgabe.erledigt }">
                            <i :class="form.aufgabe.erledigt ? 'fas fa-check-circle' : 'far fa-circle'"></i>
                            <span x-text="form.aufgabe.erledigt ? 'Erledigt' + (form.aufgabe.erledigt_am ? ' am ' + datum(form.aufgabe.erledigt_am) : '') : 'Offen'"></span>
                        </span>
                        <span class="text-xs text-slate-500" x-show.important="form.aufgabe.fortschritt" x-text="form.aufgabe.fortschritt + ' erledigt'"></span>
                        <span class="text-xs text-slate-500" x-show.important="form.aufgabe.datum" x-text="'bis ' + datum(form.aufgabe.datum)"></span>
                        <button type="button" class="erg-btn erg-btn-primary erg-btn-sm ml-auto" x-show.important="!form.aufgabe.erledigt"
                                @click="aufgabeErledigen(form.id)" :disabled="laedt">
                            <i class="fas fa-check"></i> Erledigt
                        </button>
                    </div>
                </template>
                <template x-if="!form.aufgabe && board.theme && board.darf_schreiben">
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="erg-label text-xs" for="erg-k-aufgabe-datum">Erledigen bis (optional)</label>
                            <input id="erg-k-aufgabe-datum" type="date" class="erg-input erg-input-sm" x-model="aufgabeDatum">
                        </div>
                        <button type="button" class="erg-btn erg-btn-blue erg-btn-sm" @click="aufgabeAnlegen()" :disabled="laedt">
                            <i class="fas fa-plus"></i> Als Aufgabe anlegen
                        </button>
                        <p class="erg-hint w-full" x-text="'Im Thema „' + board.theme.titel + '“; Zuständige werden übernommen.'"></p>
                    </div>
                </template>
                <template x-if="!form.aufgabe && !board.theme && board.darf_schreiben">
                    <p class="erg-hint mt-0">Verknüpfen Sie das Board unter „Einstellungen“ mit einem Thema, um aus Karten Aufgaben zu machen.</p>
                </template>
            </div>
        </div>

        <div class="erg-dialog-fuss" x-show.important="kartenReiter === 'details'">
            <div>
                <template x-if="schreibbar && form.id">
                    <button type="button" class="erg-btn erg-btn-danger erg-btn-sm" @click="karteLoeschen()" :disabled="laedt">
                        <i class="far fa-trash-alt"></i> Löschen
                    </button>
                </template>
            </div>
            <div class="flex gap-2">
                <button type="button" class="erg-btn erg-btn-ghost" @click="dialogSchliessen()" x-text="schreibbar ? 'Abbrechen' : 'Schließen'"></button>
                <template x-if="schreibbar">
                    <button type="button" class="erg-btn erg-btn-primary" @click="karteSpeichern()" :disabled="laedt">
                        <i class="fas fa-save"></i> Speichern
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
