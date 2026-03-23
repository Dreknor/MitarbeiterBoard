{{--
    PaedDiary v2 – Notiz-Editor Partial
    Inline-Editor zum Erstellen/Bearbeiten/Löschen von Einträgen
--}}
<div class="col-12 mb-2"
     x-data="noteEditor()"
     @diary-edit-entry.window="openForEdit($event.detail)"
     @diary-new-entry.window="openForNew($event.detail)">

    <div class="card shadow-sm" x-show="editorOpen" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         id="noteEditorCard"
         :class="{ 'editing': editingEntryId }"
         x-ref="editorCard">

        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <strong class="small mb-0" x-text="editingEntryId ? 'Notiz bearbeiten' : 'Notiz erfassen'"></strong>
            <button class="btn btn-sm btn-outline-secondary" @click="closeEditor()" title="Schließen">✕</button>
        </div>

        <div class="card-body py-2">
            <form @submit.prevent="saveEntry()" class="mb-0">
                <div class="form-row">
                    {{-- Datum --}}
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Datum</label>
                        <input type="date" x-model="formDate" class="form-control form-control-sm" required>
                    </div>

                    {{-- Schüler-Checkboxen --}}
                    <div class="col-md-10 mb-2">
                        <label class="small mb-1">Schüler</label>
                        <div class="border rounded p-2 bg-light" style="font-size:0.75rem;">
                            {{-- Im Gruppenmodus nach Klasse gruppieren --}}
                            <template x-if="$store.diary.is_group">
                                <div>
                                    <template x-for="klasse in $store.diary.klassen" :key="klasse.id">
                                        <div class="mb-1">
                                            <div class="text-primary font-weight-bold small border-top pt-1 mt-1"
                                                 x-text="klasse.name"></div>
                                            <template x-for="stu in $store.diary.schueler.filter(s => s.klasse_id === klasse.id)"
                                                      :key="stu.id">
                                                <label class="custom-checkbox-wrapper" style="font-size:.65rem;">
                                                    <input type="checkbox" class="custom-checkbox-input"
                                                           :value="String(stu.id)" x-model="formSchuelerIds">
                                                    <span class="custom-checkbox-label" x-text="stu.name"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            {{-- Einzelklassen-Modus --}}
                            <template x-if="!$store.diary.is_group">
                                <div>
                                    <template x-for="stu in $store.diary.schueler" :key="stu.id">
                                        <label class="custom-checkbox-wrapper" style="font-size:.65rem;">
                                            <input type="checkbox" class="custom-checkbox-input"
                                                   :value="String(stu.id)" x-model="formSchuelerIds">
                                            <span class="custom-checkbox-label" x-text="stu.name"></span>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Notiz-Text --}}
                <div class="form-group mb-2">
                    <label class="small mb-1">Notiz</label>
                    <textarea x-model="formContent" rows="3" class="form-control form-control-sm" required></textarea>
                </div>

                {{-- Kategorie --}}
                <div class="form-row">
                    <div class="col-md-6 mb-2">
                        <label class="small mb-1">Notizkategorie</label>
                        <select x-model="formCategoryId" class="form-control form-control-sm">
                            <option value="">-- Keine --</option>
                            <template x-for="cat in $store.diary.categories" :key="cat.id">
                                <option :value="String(cat.id)" x-text="cat.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="small mb-1">Neue Notizkategorie (optional)</label>
                        <input type="text" x-model="formNewCategory" class="form-control form-control-sm"
                               placeholder="Neue Notizkategorie (wird priorisiert)">
                    </div>
                </div>

                {{-- Erledigt & Dossier --}}
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="form-group mb-2">
                            <input type="checkbox" x-model="formCompleted" value="1"
                                   class="align-middle" style="vertical-align:middle;"
                                   :id="'noteCompletedV2'">
                            <label for="noteCompletedV2" class="small mb-0 align-middle" style="vertical-align:middle;">Erledigt</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-group mb-2">
                            <input type="checkbox" x-model="formDossierOnly" value="1"
                                   class="align-middle" style="vertical-align:middle;"
                                   :id="'dossierOnlyV2'">
                            <label for="dossierOnlyV2" class="small mb-0 align-middle" style="vertical-align:middle;">nur für Schüler-Ansicht</label>
                        </div>
                    </div>
                </div>

                {{-- Aktionsbuttons --}}
                <div class="d-flex align-items-center flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm mr-2" :disabled="saving">
                        <span x-show="saving"><i class="fas fa-spinner fa-spin"></i></span>
                        Speichern
                    </button>
                    <button type="button" class="btn btn-danger btn-sm mr-2"
                            x-show="editingEntryId" x-cloak
                            @click="deleteEntry()">Löschen</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetForm()">Neu</button>
                    <span class="text-muted small ml-3" x-text="statusText"></span>
                </div>
            </form>
        </div>
    </div>
</div>

