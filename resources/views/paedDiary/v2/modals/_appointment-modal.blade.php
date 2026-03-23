{{--
    PaedDiary v2 – Termin-Modal
    Bootstrap-4-Modal für Termine (CRUD)
--}}
<div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog"
     x-data="appointmentManager()"
     @edit-appointment.window="openEditModal($event.detail)">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" x-text="formId ? 'Termin bearbeiten' : 'Termin erstellen'"></h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form @submit.prevent="saveAppointment()">
                <div class="modal-body p-3">
                    {{-- Feedback --}}
                    <div x-show="appointmentFeedback" x-cloak class="alert alert-warning small p-2 mb-2" x-text="appointmentFeedback"></div>

                    <div class="form-group mb-2">
                        <label class="small mb-1">Titel</label>
                        <input type="text" x-model="formTitle" class="form-control form-control-sm" required maxlength="200">
                    </div>

                    <div class="form-row">
                        <div class="col-md-4 mb-2">
                            <label class="small mb-1">Datum</label>
                            <input type="date" x-model="formStartDate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="small mb-1">Startzeit</label>
                            <input type="time" x-model="formStartTime" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="small mb-1">Endzeit</label>
                            <input type="time" x-model="formEndTime" class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label class="small mb-1">Beschreibung</label>
                        <textarea x-model="formDescription" rows="3" class="form-control form-control-sm"></textarea>
                    </div>

                    {{-- Wiederkehrend --}}
                    <div class="form-group mb-2">
                        <input type="checkbox" x-model="formIsRecurring" value="1" class="align-middle" id="aptRecurringV2">
                        <label for="aptRecurringV2" class="small mb-0 align-middle">Wiederkehrender Termin</label>
                    </div>

                    <div x-show="formIsRecurring" x-cloak>
                        <div class="form-row">
                            <div class="col-md-4 mb-2">
                                <label class="small mb-1">Wiederholung</label>
                                <select x-model="formRecurringType" class="form-control form-control-sm">
                                    <option value="daily">Täglich</option>
                                    <option value="weekly">Wöchentlich</option>
                                    <option value="monthly">Monatlich</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="small mb-1">Intervall</label>
                                <input type="number" x-model="formRecurringInterval" class="form-control form-control-sm" min="1">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="small mb-1">Ende der Wiederholung</label>
                                <input type="date" x-model="formRecurringEndDate" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>

                    {{-- Zuweisen an --}}
                    <div class="form-group mb-2">
                        <label class="small mb-1">Zuweisen an</label>
                        <div class="border rounded p-2 bg-light" style="font-size:0.75rem;">
                            {{-- Klassen --}}
                            <div class="mb-2">
                                <strong>Klassen:</strong>
                                @foreach($klassen as $k)
                                    <div class="form-check-inline mb-1">
                                        <input class="form-check-input" type="checkbox"
                                               value="{{ $k->id }}"
                                               x-model="formKlasseIds">
                                        <label class="form-check-label">{{ $k->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                            {{-- Gruppen --}}
                            <div class="mb-2">
                                <strong>Gruppen:</strong>
                                @foreach($groups as $g)
                                    <div class="form-check-inline mb-1">
                                        <input class="form-check-input" type="checkbox"
                                               value="{{ $g->id }}"
                                               x-model="formGroupIds">
                                        <label class="form-check-label">{{ $g->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                            {{-- Schüler --}}
                            <div class="mb-2">
                                <strong>Schüler:</strong>
                                <div class="mt-1" style="max-height:200px; overflow-y:auto;">
                                    <template x-for="stu in $store.diary.schueler" :key="stu.id">
                                        <div class="form-check-inline mb-1">
                                            <input class="form-check-input" type="checkbox"
                                                   :value="String(stu.id)"
                                                   x-model="formSchuelerIds">
                                            <label class="form-check-label" x-text="stu.name"></label>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="appointmentSaving">
                        <span x-show="appointmentSaving"><i class="fas fa-spinner fa-spin"></i></span>
                        Speichern
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" x-show="formId" x-cloak
                            @click="deleteAppointment()">Löschen</button>
                    <button type="button" class="btn btn-warning btn-sm" x-show="formId" x-cloak
                            @click="togglePause()">Pausieren</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Schließen</button>
                </div>
            </form>
        </div>
    </div>
</div>

