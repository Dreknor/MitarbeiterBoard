{{-- Termin-Erstellen/Bearbeiten-Modal --}}
<div x-show="showCreateModal || editingEvent"
     x-cloak
     @keydown.escape.window="showCreateModal = false; editingEvent = null"
     class="cal-modal-backdrop"
     @click.self="showCreateModal = false; editingEvent = null">

    <div class="bg-white rounded-lg shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6"
         @click.stop
         x-data="terminForm()"
         x-effect="syncFromParent($root.editingEvent)">

        <form :action="formAction" method="POST" @submit.prevent="prepareAndSubmit">
            @csrf

            <template x-if="$root.editingEvent">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div class="flex justify-between items-start mb-4">
                <h2 class="text-lg font-semibold text-gray-900"
                    x-text="$root.editingEvent ? 'Termin bearbeiten' : 'Neuen Termin erstellen'"></h2>
                <button type="button"
                        @click="$root.showCreateModal = false; $root.editingEvent = null"
                        class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            {{-- Kalender-Auswahl --}}
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kalender *</label>
                <select name="ox_calendar_id" x-model="formData.ox_calendar_id"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                    <option value="">Bitte wählen…</option>
                    @foreach($schreibbareKalender ?? [] as $cal)
                        <option value="{{ $cal->id }}">{{ $cal->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Titel --}}
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel *</label>
                <input type="text" name="titel" x-model="formData.titel"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                       maxlength="255" required>
            </div>

            {{-- Ganztägig-Toggle --}}
            <div class="mb-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="ganztaegig" x-model="formData.ganztaegig"
                           value="1" class="rounded">
                    <span class="text-sm">Ganztägig</span>
                </label>
            </div>

            {{-- Datum/Uhrzeit --}}
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <span x-text="formData.ganztaegig ? 'Datum' : 'Beginn'"></span> *
                    </label>
                    <input :type="formData.ganztaegig ? 'date' : 'datetime-local'"
                           name="beginn" x-model="formData.beginn"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <span x-text="formData.ganztaegig ? 'Bis Datum' : 'Ende'"></span> *
                    </label>
                    <input :type="formData.ganztaegig ? 'date' : 'datetime-local'"
                           name="ende" x-model="formData.ende"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                </div>
            </div>

            {{-- Ort --}}
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                <input type="text" name="ort" x-model="formData.ort"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                       maxlength="255">
            </div>

            {{-- Beschreibung --}}
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="beschreibung" x-model="formData.beschreibung" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                          maxlength="5000"></textarea>
            </div>

            {{-- Wiederholung (RRULE) --}}
            <div class="mb-3 border-t border-gray-200 pt-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Wiederholung</label>
                <select x-model="recurrence.type"
                        @change="updateRrule()"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="none">Keine</option>
                    <option value="daily">Täglich</option>
                    <option value="weekly">Wöchentlich</option>
                    <option value="monthly">Monatlich</option>
                    <option value="custom">Benutzerdefiniert…</option>
                </select>

                {{-- Benutzerdefinierte Optionen --}}
                <template x-if="recurrence.type === 'custom'">
                    <div class="mt-2 space-y-2 p-3 bg-gray-50 rounded">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">Alle</span>
                            <input type="number" x-model.number="recurrence.interval"
                                   min="1" max="99"
                                   class="w-16 border border-gray-300 rounded px-2 py-1 text-sm"
                                   @change="updateRrule()">
                            <select x-model="recurrence.frequency"
                                    class="border border-gray-300 rounded px-2 py-1 text-sm"
                                    @change="updateRrule()">
                                <option value="DAILY">Tag(e)</option>
                                <option value="WEEKLY">Woche(n)</option>
                                <option value="MONTHLY">Monat(e)</option>
                                <option value="YEARLY">Jahr(e)</option>
                            </select>
                        </div>

                        {{-- Wochentage (nur bei WEEKLY) --}}
                        <template x-if="recurrence.frequency === 'WEEKLY'">
                            <div class="flex gap-2 flex-wrap">
                                <template x-for="day in ['MO','DI','MI','DO','FR','SA','SO']" :key="day">
                                    <label class="flex items-center gap-1 text-sm cursor-pointer">
                                        <input type="checkbox" :value="dayMap[day]"
                                               @change="toggleDay(dayMap[day]); updateRrule()"
                                               :checked="recurrence.byDay.includes(dayMap[day])"
                                               class="rounded">
                                        <span x-text="day"></span>
                                    </label>
                                </template>
                            </div>
                        </template>

                        {{-- Endbedingung --}}
                        <div class="space-y-1">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="recurrence.endType" value="never"
                                       @change="updateRrule()">
                                Nie
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="recurrence.endType" value="until"
                                       @change="updateRrule()">
                                Am:
                                <input type="date" x-model="recurrence.until"
                                       @change="updateRrule()"
                                       class="border border-gray-300 rounded px-2 py-1 text-sm"
                                       :disabled="recurrence.endType !== 'until'">
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" x-model="recurrence.endType" value="count"
                                       @change="updateRrule()">
                                Nach
                                <input type="number" x-model.number="recurrence.count"
                                       min="1" max="999"
                                       @change="updateRrule()"
                                       class="w-16 border border-gray-300 rounded px-2 py-1 text-sm"
                                       :disabled="recurrence.endType !== 'count'">
                                Terminen
                            </label>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Hidden: RRULE + expected_updated_at --}}
            <input type="hidden" name="rrule" :value="formData.rrule">
            <template x-if="$root.editingEvent">
                <input type="hidden" name="expected_updated_at" :value="$root.editingEvent && $root.editingEvent.updated_at">
            </template>

            {{-- Validierungs-Fehler --}}
            @if($errors->any())
                <div class="mb-3 p-3 bg-red-50 border border-red-300 rounded text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Buttons --}}
            <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-200">
                <button type="button"
                        @click="$root.showCreateModal = false; $root.editingEvent = null"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</div>

