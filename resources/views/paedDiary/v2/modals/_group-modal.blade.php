{{--
    PaedDiary v2 – Gruppen-Verwaltungsmodal
    Bootstrap-4-Modal für Klassenkopplungen (CRUD)
--}}
<div class="modal fade" id="groupModal" tabindex="-1" role="dialog" x-data="groupManager()">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Klassenkopplungen verwalten</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-2">
                {{-- Feedback --}}
                <div x-show="groupFeedback" x-cloak class="small mb-2"
                     :class="{
                         'text-success': groupFeedbackType === 'success',
                         'text-warning': groupFeedbackType === 'warning',
                         'text-danger': groupFeedbackType === 'danger',
                         'text-info': groupFeedbackType === 'info'
                     }"
                     x-text="groupFeedback"></div>

                {{-- Formular --}}
                <form @submit.prevent="saveGroup()" class="border rounded p-2 mb-3">
                    <div class="form-row">
                        <div class="col-md-4 mb-2">
                            <label class="small mb-1">Name</label>
                            <input type="text" x-model="formName" class="form-control form-control-sm" maxlength="80" required>
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="small mb-1">Klassen wählen (mind. 2)</label>
                            <div class="border rounded p-2 bg-light" style="font-size:0.75rem;">
                                @foreach($klassen as $k)
                                    <div class="form-check-inline mb-1">
                                        <input class="form-check-input" type="checkbox"
                                               value="{{ $k->id }}"
                                               :checked="isKlasseSelected({{ $k->id }})"
                                               @change="toggleKlasse({{ $k->id }})">
                                        <label class="form-check-label">{{ $k->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center">
                        <button class="btn btn-primary btn-sm mr-2" type="submit">Speichern</button>
                        <button class="btn btn-secondary btn-sm mr-2" type="button"
                                x-show="formId" x-cloak @click="cancelEdit()">Abbrechen</button>
                        <span class="small text-muted" x-text="groupStatusText"></span>
                    </div>
                </form>

                {{-- Liste bestehender Gruppen --}}
                <h6 class="small font-weight-bold">Bestehende Kopplungen</h6>
                <div class="small">
                    <template x-if="$store.diary.groups.length === 0">
                        <span class="text-muted">Keine Kopplungen</span>
                    </template>
                    <template x-for="g in $store.diary.groups" :key="g.id">
                        <div class="border rounded p-2 mb-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong x-text="g.name"></strong><br>
                                    <span class="text-muted" x-text="(g.klassen || []).map(k => k.name).join(', ')"></span>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary"
                                            @click="editGroup(g)" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger"
                                            @click="deleteGroup(g.id)" title="Löschen">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

