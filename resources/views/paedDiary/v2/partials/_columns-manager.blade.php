{{--
    PaedDiary v2 – Spalten-Verwaltung Partial
    Card zum Verwalten der Zusatzspalten (Erstellen, Bearbeiten, Kopieren, Deaktivieren, Reaktivieren)
--}}
<div class="col-12 mb-3" x-data="columnsManager()" data-klassen='@json($klassen->map(fn ($k) => ["id" => $k->id, "name" => $k->name]))' x-show="$store.diary.columnsCardOpen" x-cloak>
    <div class="card shadow-sm">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong class="small mb-0">Spalten verwalten</strong>
            <button class="btn btn-sm btn-outline-secondary" @click="$store.diary.columnsCardOpen = false" title="Schließen">✕</button>
        </div>
        <div class="card-body py-2">
            {{-- Feedback --}}
            <div x-show="columnsFeedback" x-cloak class="small mb-2"
                 :class="{
                     'text-success': columnsFeedbackType === 'success',
                     'text-warning': columnsFeedbackType === 'warning',
                     'text-danger': columnsFeedbackType === 'danger',
                     'text-info': columnsFeedbackType === 'info'
                 }"
                 x-text="columnsFeedback"></div>

            {{-- Deaktivierte anzeigen Toggle --}}
            <div class="mb-2">
                <input type="checkbox" x-model="showDeactivated" id="showDeactivatedV2" class="mr-2" style="vertical-align:middle">
                <label for="showDeactivatedV2" class="small mr-3">Deaktivierte anzeigen</label>
            </div>

            {{-- Bestehende Spalten (gruppiert nach Kategorie) --}}
            <h6 class="mb-2 small font-weight-bold">Spalten verwalten</h6>
            <div class="mb-2 d-flex flex-wrap align-items-start">
                <template x-for="[catName, cols] in groupedColumns" :key="catName">
                    <div class="column-category mr-3 mb-2">
                        <div class="small text-primary font-weight-bold mb-1">
                            <span x-text="catName"></span>
                            {{-- „Gilt bis Freitag“: für die ganze Spaltengruppe (alle Klassen), nur Ja/Nein- und Ampel-Spalten --}}
                            <template x-if="catName !== 'Unkategorisiert' && darfBisWochenende">
                                <label class="ml-2 mb-0 font-weight-normal text-dark" title="Bestätigungen gelten ab dem Tag der Änderung bis Freitag (Ja/Nein- und Ampel-Spalten, alle Klassen)">
                                    <input type="checkbox" class="mr-1" style="vertical-align:middle"
                                           :checked="gruppeNameBisWochenende(catName)"
                                           @change="setGruppeBisWochenende(catName, $event.target.checked)">
                                    gilt bis Freitag
                                </label>
                            </template>
                            <template x-if="catName !== 'Unkategorisiert' && !darfBisWochenende && gruppeNameBisWochenende(catName)">
                                <span class="badge badge-info ml-2 font-weight-normal" title="Bestätigungen gelten ab dem Tag der Änderung bis Freitag">gilt bis Freitag</span>
                            </template>
                        </div>
                        <div class="column-category-list d-flex flex-wrap">
                            <template x-for="col in cols" :key="col.id">
                                <div class="column-chip" :class="{ 'deactivated': !!col.deactivated_from }">
                                    <div class="d-flex align-items-center">
                                        <span class="mr-2" x-text="col.name"></span>
                                        {{-- Bearbeiten-Button --}}
                                        <button type="button" class="btn btn-link btn-sm ml-1 p-0" @click="bearbeitenUmschalten(col.id)" title="Bearbeiten (Name, Gruppe, Art, Klassen)">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        {{-- Kopieren-Button --}}
                                        <button type="button" class="btn btn-link btn-sm ml-1 p-0" @click="toggleCopyPanel(col.id)" title="In andere Klassen kopieren">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        {{-- Deaktivieren-Button --}}
                                        <template x-if="!col.deactivated_from">
                                            <button type="button" class="remove-col btn btn-link btn-sm ml-2"
                                                    @click="deactivateColumn(col.id)" title="Deaktivieren">&times;</button>
                                        </template>
                                        {{-- Reaktivieren-Button --}}
                                        <template x-if="col.deactivated_from">
                                            <button type="button" class="restore restore-col btn btn-link btn-sm ml-2"
                                                    @click="restoreColumn(col.id)" title="Reaktivieren">&#8634;</button>
                                        </template>
                                    </div>
                                    {{-- Bearbeiten-Panel --}}
                                    <div x-show="bearbeitenFuer === col.id" x-cloak class="border rounded p-2 mt-1 bg-light" style="min-width:240px;max-width:360px;">
                                        <div class="small font-weight-bold mb-1">Spalte bearbeiten</div>
                                        <div class="small text-muted" x-show="!bearbeitung && !bearbeitungFehler">Wird geladen …</div>
                                        <template x-if="bearbeitung && bearbeitung.id === col.id">
                                            <form @submit.prevent="bearbeitungSpeichern()" class="small">
                                                <div class="form-group mb-1">
                                                    <label class="mb-0" :for="'bearb-name-' + col.id">Name</label>
                                                    <input type="text" class="form-control form-control-sm" :id="'bearb-name-' + col.id"
                                                           x-model="bearbeitungForm.name" maxlength="50" required :disabled="bearbeitung.eigenschaften_gesperrt">
                                                </div>
                                                <div class="form-group mb-1">
                                                    <label class="mb-0" :for="'bearb-gruppe-' + col.id">Spaltengruppe</label>
                                                    <input type="text" class="form-control form-control-sm" :id="'bearb-gruppe-' + col.id"
                                                           x-model="bearbeitungForm.category" maxlength="50" :list="'bearb-gruppen-' + col.id"
                                                           placeholder="leer = ohne Gruppe" :disabled="bearbeitung.eigenschaften_gesperrt">
                                                    <datalist :id="'bearb-gruppen-' + col.id">
                                                        <template x-for="g in bearbeitungKategorien" :key="'bg' + col.id + g"><option :value="g"></option></template>
                                                    </datalist>
                                                </div>
                                                <div class="form-group mb-1">
                                                    <label class="mb-0" :for="'bearb-typ-' + col.id">Art</label>
                                                    <select class="form-control form-control-sm" :id="'bearb-typ-' + col.id" x-model="bearbeitungForm.type"
                                                            :disabled="bearbeitung.eigenschaften_gesperrt || !bearbeitung.typ_aenderbar">
                                                        <option value="boolean">Ja/Nein</option>
                                                        <option value="ampel">Ampel (Ja/In Bearbeitung/Nein)</option>
                                                        <option value="text">Text</option>
                                                        <option value="number" x-show="bearbeitung.type === 'number'">Zahl</option>
                                                    </select>
                                                    <div class="text-muted" x-show="!bearbeitung.typ_aenderbar">Die Art ist fest, weil schon Werte eingetragen sind.</div>
                                                </div>
                                                <div class="text-muted mb-1" x-show="bearbeitung.eigenschaften_gesperrt"
                                                     x-text="'Name, Gruppe und Art betreffen ' + bearbeitung.weitere_klassen + ' weitere Klasse(n), die du nicht bearbeiten darfst.'"></div>
                                                <div class="font-weight-bold mt-2 mb-1">Angezeigt in Klasse(n):</div>
                                                <template x-for="k in bearbeitung.klassen" :key="'bk' + col.id + k.id">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" :id="'bearb-k-' + col.id + '-' + k.id"
                                                               :checked="bearbeitungForm.klasse_ids.includes(k.id)"
                                                               @change="bearbeitungKlasseUmschalten(k.id)">
                                                        <label class="form-check-label" :for="'bearb-k-' + col.id + '-' + k.id">
                                                            <span x-text="k.name"></span>
                                                            <span class="text-muted" x-show="bearbeitungKlasseHinweis(k)" x-text="'– ' + bearbeitungKlasseHinweis(k)"></span>
                                                        </label>
                                                    </div>
                                                </template>
                                                <div class="text-muted" x-show="bearbeitung.weitere_klassen > 0" x-text="'+ ' + bearbeitung.weitere_klassen + ' weitere Klasse(n)'"></div>
                                                <div class="text-danger mt-1" x-show="bearbeitungFehler" x-text="bearbeitungFehler"></div>
                                                <div class="mt-2">
                                                    <button type="submit" class="btn btn-sm btn-primary" :disabled="bearbeitungSpeichert || !bearbeitungForm.name.trim()">Speichern</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="bearbeitenSchliessen()">Abbrechen</button>
                                                </div>
                                            </form>
                                        </template>
                                        <div class="small text-danger" x-show="!bearbeitung && bearbeitungFehler" x-text="bearbeitungFehler"></div>
                                    </div>
                                    {{-- Kopier-Panel --}}
                                    <div x-show="copyOpenFor === col.id" x-cloak class="copy-panel border rounded p-2 mt-1 bg-light" style="min-width:180px;">
                                        <div class="small font-weight-bold mb-1">In Klasse(n) kopieren:</div>
                                        <template x-for="k in copyTargetsFor(col)" :key="k.id">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" :id="'copy-' + col.id + '-' + k.id"
                                                       :checked="copyTargetIds.includes(parseInt(k.id))"
                                                       @change="toggleCopyTarget(k.id)">
                                                <label class="form-check-label small" :for="'copy-' + col.id + '-' + k.id" x-text="k.name"></label>
                                            </div>
                                        </template>
                                        <div class="mt-1">
                                            <button type="button" class="btn btn-sm btn-primary" :disabled="!copyTargetIds.length" @click="copyColumn(col.id)">Kopieren</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleCopyPanel(col.id)">Abbrechen</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Neue Spalte anlegen --}}
            <form @submit.prevent="addColumn()" class="form-inline small mb-2">
                <input type="text" x-model="newColumnName" class="form-control form-control-sm mr-1 mb-1"
                       placeholder="Name" required maxlength="50">
                <select x-model="newColumnCategorySelect" class="form-control form-control-sm mr-1 mb-1">
                    <option value="">-- Keine / Neue --</option>
                    <template x-for="cat in existingColumnCategories" :key="cat">
                        <option :value="cat" x-text="cat"></option>
                    </template>
                </select>
                <input type="text" x-model="newColumnNewCategory" class="form-control form-control-sm mr-1 mb-1"
                       placeholder="Neue Spaltengruppe (optional)">
                <select x-model="newColumnType" class="form-control form-control-sm mr-1 mb-1">
                    <option value="boolean">Ja/Nein</option>
                    <option value="ampel">Ampel (Ja/In Bearbeitung/Nein)</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary mb-1">Hinzufügen</button>
            </form>

            {{-- Ziel-Klassen für neue Spalte (Mehrfachauswahl) --}}
            <div class="mb-2">
                <div class="small font-weight-bold mb-1">Anlegen für Klasse(n):</div>
                <div class="d-flex flex-wrap">
                    <template x-for="k in allKlassenList" :key="k.id">
                        <div class="form-check mr-3">
                            <input type="checkbox" class="form-check-input" :id="'newcol-klasse-' + k.id"
                                   :checked="newColumnKlasseIds.includes(parseInt(k.id))"
                                   @change="toggleNewColumnKlasse(k.id)">
                            <label class="form-check-label small" :for="'newcol-klasse-' + k.id" x-text="k.name"></label>
                        </div>
                    </template>
                </div>
            </div>


            <div class="text-muted small">Löschen deaktiviert die Spalte ab der aktuellen Woche (Werte ab dieser Woche werden entfernt). Historische Wochen bleiben erhalten.</div>
        </div>
    </div>
</div>

