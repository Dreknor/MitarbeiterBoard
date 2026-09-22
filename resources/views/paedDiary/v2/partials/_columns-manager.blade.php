{{--
    PaedDiary v2 – Spalten-Verwaltung Partial
    Card zum Verwalten der Zusatzspalten (Erstellen, Deaktivieren, Reaktivieren)
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
                        <div class="small text-primary font-weight-bold mb-1" x-text="catName"></div>
                        <div class="column-category-list d-flex flex-wrap">
                            <template x-for="col in cols" :key="col.id">
                                <div class="column-chip" :class="{ 'deactivated': !!col.deactivated_from }">
                                    <div class="d-flex align-items-center">
                                        <span class="mr-2" x-text="col.name"></span>
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

