{{--
    PaedDiary v2 – Spalten-Verwaltung Partial
    Card zum Verwalten der Zusatzspalten (Erstellen, Deaktivieren, Reaktivieren)
--}}
<div class="col-12 mb-3" x-data="columnsManager()" x-show="$store.diary.columnsCardOpen" x-cloak>
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
                </select>
                <button type="submit" class="btn btn-sm btn-primary mb-1">Hinzufügen</button>
            </form>

            <div class="text-muted small">Löschen deaktiviert die Spalte ab der aktuellen Woche (Werte ab dieser Woche werden entfernt). Historische Wochen bleiben erhalten.</div>
        </div>
    </div>
</div>

