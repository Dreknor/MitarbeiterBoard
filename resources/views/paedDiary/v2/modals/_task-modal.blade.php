{{--
    PaedDiary v2 – Aufgaben-Modal
    Bootstrap-4-Modal für Aufgaben-CRUD
--}}
<div class="modal fade" id="taskModal" tabindex="-1" role="dialog" x-data="taskPanel()">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" x-text="editingTaskId ? 'Aufgabe bearbeiten' : 'Aufgabe erstellen'"></h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form @submit.prevent="saveTask()">
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="small mb-1">Titel</label>
                        <input type="text" x-model="taskFormTitle" class="form-control form-control-sm" required maxlength="200">
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-1">Beschreibung (optional)</label>
                        <textarea x-model="taskFormDescription" rows="3" class="form-control form-control-sm"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Fälligkeitsdatum</label>
                            <input type="date" x-model="taskFormDueDate" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 mb-2 d-flex align-items-end">
                            <div>
                                <input type="checkbox" x-model="taskFormHighlighted" value="1" id="taskHighlightedV2" class="mr-1">
                                <label for="taskHighlightedV2" class="small mb-0">Hervorgehoben</label>
                            </div>
                        </div>
                    </div>

                    {{-- Schüler-Auswahl --}}
                    <div class="form-group mb-2">
                        <label class="small mb-1">Zuweisen an</label>
                        <div class="border rounded p-2 bg-light" style="font-size:0.75rem; max-height:200px; overflow-y:auto;">
                            <template x-for="klasse in $store.diary.klassen" :key="klasse.id">
                                <div class="mb-2">
                                    <strong class="small d-block" x-text="klasse.name"></strong>
                                    <template x-for="stu in $store.diary.schueler.filter(s => s.klasse_id === klasse.id)" :key="stu.id">
                                        <div class="form-check mb-1" style="display:flex; align-items:center;">
                                            <input type="checkbox" class="form-check-input task-stu-checkbox"
                                                   :value="String(stu.id)"
                                                   x-model="taskFormSchuelerIds"
                                                   style="display:inline-block;width:14px;height:14px;position:relative;">
                                            <label class="form-check-label small" style="margin:0 0 0 .4rem;" x-text="stu.name"></label>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="taskSaving">
                        <span x-show="taskSaving"><i class="fas fa-spinner fa-spin"></i></span>
                        Speichern
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Schließen</button>
                </div>
            </form>
        </div>
    </div>
</div>

