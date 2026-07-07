{{--
    PaedDiary v2 – Termin-Modal
    Bootstrap-4-Modal für Termine (CRUD) inkl. Lösch-Dialog
--}}
<div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog"
     x-data="appointmentManager()"
     @edit-appointment.window="openEditModal($event.detail)">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"
                    x-text="showingDeleteOptions ? 'Termin löschen' : (formId ? 'Termin bearbeiten' : 'Termin erstellen')"></h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>

            {{-- ===== LÖSCH-BESTÄTIGUNG (Tailwind) ===== --}}
            <div x-show="showingDeleteOptions" x-cloak>
                <div class="p-5">

                    {{-- Fehler-Feedback --}}
                    <div x-show="appointmentFeedback" x-cloak
                         class="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        <i class="fas fa-exclamation-triangle mt-0.5 flex-shrink-0"></i>
                        <span x-text="appointmentFeedback"></span>
                    </div>

                    {{-- Header --}}
                    <div class="flex items-center gap-3 mb-5">
                        <div class="flex items-center justify-center w-11 h-11 rounded-full bg-red-100 flex-shrink-0">
                            <i class="fas fa-trash text-red-600 text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 leading-tight" x-text="'«' + formTitle + '» löschen'"></h3>
                            <p class="text-xs text-gray-500 mt-0.5">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                        </div>
                    </div>

                    {{-- ── Vorkommen-Scope (nur bei wiederkehrenden Terminen) ── --}}
                    <div x-show="formIsRecurring" x-cloak class="mb-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Welche Termine löschen?</p>
                        <div class="flex flex-col gap-2">

                            {{-- Nur diesen --}}
                            <label class="flex items-start gap-3 rounded-lg border-2 p-3 cursor-pointer transition-colors"
                                   :class="deleteMode === 'only_this'
                                       ? 'border-red-400 bg-red-50'
                                       : 'border-gray-200 bg-white hover:border-gray-300'">
                                <input type="radio" value="only_this" x-model="deleteMode" class="mt-0.5 accent-red-500 flex-shrink-0">
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Nur diesen Termin</span>
                                    <span class="ml-1 text-xs text-gray-400"
                                          x-text="formStartDate ? '(' + new Date(formStartDate + 'T00:00:00').toLocaleDateString('de-DE') + ')' : ''"></span>
                                    <p class="text-xs text-gray-500 mt-0.5">Alle anderen Vorkommen bleiben erhalten.</p>
                                </div>
                            </label>

                            {{-- Dieser + zukünftige --}}
                            <label class="flex items-start gap-3 rounded-lg border-2 p-3 cursor-pointer transition-colors"
                                   :class="deleteMode === 'this_and_future'
                                       ? 'border-red-400 bg-red-50'
                                       : 'border-gray-200 bg-white hover:border-gray-300'">
                                <input type="radio" value="this_and_future" x-model="deleteMode" class="mt-0.5 accent-red-500 flex-shrink-0">
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Dieser und alle zukünftigen Termine</span>
                                    <p class="text-xs text-gray-500 mt-0.5">Vergangene Vorkommen bleiben erhalten.</p>
                                </div>
                            </label>

                            {{-- Alle --}}
                            <label class="flex items-start gap-3 rounded-lg border-2 p-3 cursor-pointer transition-colors"
                                   :class="deleteMode === 'all'
                                       ? 'border-red-400 bg-red-50'
                                       : 'border-gray-200 bg-white hover:border-gray-300'">
                                <input type="radio" value="all" x-model="deleteMode" class="mt-0.5 accent-red-500 flex-shrink-0">
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Alle Termine dieser Reihe</span>
                                    <p class="text-xs text-gray-500 mt-0.5">Die gesamte Terminserie wird gelöscht.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- ── Schüler-Scope ── --}}
                    <div x-show="formSchuelerIds.length > 0" x-cloak class="mb-5">
                        <div class="border-t border-gray-100 pt-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Für wen löschen?</p>
                            <div class="flex flex-col gap-2">

                                <label class="flex items-start gap-3 rounded-lg border-2 p-3 cursor-pointer transition-colors"
                                       :class="deleteSchuelerScope === 'all'
                                           ? 'border-red-400 bg-red-50'
                                           : 'border-gray-200 bg-white hover:border-gray-300'">
                                    <input type="radio" value="all" x-model="deleteSchuelerScope" class="mt-0.5 accent-red-500 flex-shrink-0">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">Für alle zugeordneten Schüler</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 rounded-lg border-2 p-3 cursor-pointer transition-colors"
                                       :class="deleteSchuelerScope === 'specific'
                                           ? 'border-red-400 bg-red-50'
                                           : 'border-gray-200 bg-white hover:border-gray-300'">
                                    <input type="radio" value="specific" x-model="deleteSchuelerScope" class="mt-0.5 accent-red-500 flex-shrink-0">
                                    <div class="w-full">
                                        <span class="text-sm font-medium text-gray-800">Nur für bestimmte Schüler entfernen</span>

                                        {{-- Schüler-Checkboxen --}}
                                        <div x-show="deleteSchuelerScope === 'specific'" x-cloak
                                             class="mt-2 max-h-36 overflow-y-auto rounded-md border border-gray-200 bg-white divide-y divide-gray-100">
                                            <template x-for="stu in $store.diary.schueler.filter(s => formSchuelerIds.includes(String(s.id)))" :key="stu.id">
                                                <label class="flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-gray-50 transition-colors">
                                                    <input type="checkbox"
                                                           :value="String(stu.id)"
                                                           x-model="deleteSchuelerIds"
                                                           class="accent-red-500 flex-shrink-0">
                                                    <span class="text-sm text-gray-700" x-text="stu.name"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Einfache Bestätigung (kein Recurring, keine Schüler) --}}
                    <div x-show="!formIsRecurring && formSchuelerIds.length === 0" x-cloak
                         class="mb-5 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                        <span>Diesen Termin wirklich unwiderruflich löschen?</span>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button"
                                @click="cancelDelete()"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition-colors">
                            Abbrechen
                        </button>
                        <button type="button"
                                @click="confirmDelete()"
                                :disabled="deleteSchuelerScope === 'specific' && deleteSchuelerIds.length === 0"
                                class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center gap-2">
                            <i class="fas fa-trash text-xs"></i>
                            Endgültig löschen
                        </button>
                    </div>
                </div>
            </div>

            {{-- ===== FORMULAR ===== --}}
            <form @submit.prevent="saveAppointment()" x-show="!showingDeleteOptions">
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
                            @click="showDeleteDialog()">
                        <i class="fas fa-trash"></i> Löschen
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" x-show="formId" x-cloak
                            @click="togglePause()">Pausieren</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Schließen</button>
                </div>
            </form>
        </div>
    </div>
</div>
