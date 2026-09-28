{{-- Termin-Erstellen/Bearbeiten-Modal --}}
<div x-data="terminForm"
     x-show="offen"
     x-cloak
     @termin-form-open.window="oeffnen($event.detail)"
     @keydown.escape.window="offen && schliessen()"
     class="cal-modal-backdrop"
     @click.self="schliessen()">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col" @click.stop
         role="dialog" aria-modal="true" aria-labelledby="termin-form-titel">

        <form :action="formAction" method="POST" @submit="vorAbsenden($event)" class="flex flex-col min-h-0">
            @csrf
            <input type="hidden" name="_formular" value="1">
            <template x-if="terminId">
                <div>
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="_termin_id" :value="terminId">
                    <input type="hidden" name="expected_updated_at" :value="updatedAt">
                </div>
            </template>
            <template x-if="terminId && canBookRooms">
                <input type="hidden" name="raum_aendern" value="1">
            </template>
            <input type="hidden" name="rrule" :value="form.rrule">
            <input type="hidden" name="ganztaegig" :value="form.ganztaegig ? 1 : 0">

            {{-- Kopf --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h2 id="termin-form-titel" class="text-lg font-semibold text-gray-900"
                    x-text="terminId ? 'Termin bearbeiten' : 'Neuen Termin erstellen'"></h2>
                <button type="button" @click="schliessen()"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Schließen">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="px-6 py-4 overflow-y-auto space-y-4">

                {{-- Validierungs-Fehler (nach Redirect) --}}
                @if($errors->any() && old('_formular'))
                    <div class="p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Kalender (Mehrfachauswahl) --}}
                <fieldset>
                    <legend class="cal-label">
                        Kalender *
                        <span class="font-normal text-gray-500">– der Termin wird in jeden gewählten Kalender in OX eingetragen</span>
                    </legend>
                    <div class="grid grid-cols-2 max-sm:grid-cols-1 gap-1.5">
                        @foreach($schreibbareKalender as $cal)
                            <label class="flex items-center gap-2 px-2.5 py-1.5 border rounded-md cursor-pointer transition-colors"
                                   :class="form.kalender_ids.includes({{ $cal->id }}) ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="checkbox" name="kalender_ids[]" value="{{ $cal->id }}"
                                       x-model.number="form.kalender_ids"
                                       class="w-4 h-4 shrink-0 accent-blue-600">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $cal->farbe }}"></span>
                                <span class="text-sm text-gray-800 truncate">{{ $cal->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <template x-if="fremdeKalender.length > 0">
                        <p class="mt-1.5 text-xs text-gray-500">
                            Außerdem eingetragen in (ohne Schreibrecht, bleibt unverändert):
                            <span x-text="fremdeKalender.map(k => k.name).join(', ')"></span>
                        </p>
                    </template>
                    <p x-show="form.kalender_ids.length === 0" class="mt-1 text-xs text-red-600">
                        Bitte mindestens einen Kalender auswählen.
                    </p>
                </fieldset>

                {{-- Titel --}}
                <div>
                    <label for="tf-titel" class="cal-label">Titel *</label>
                    <input id="tf-titel" type="text" name="titel" x-model="form.titel" x-ref="titel"
                           class="cal-input" maxlength="255" required>
                </div>

                {{-- Zeit --}}
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg space-y-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" x-model="form.ganztaegig" @change="ganztaegigUmschalten()" class="w-4 h-4 accent-blue-600">
                        <span class="text-sm text-gray-800">Ganztägig</span>
                    </label>
                    <div class="grid grid-cols-2 max-sm:grid-cols-1 gap-3">
                        <div>
                            <label for="tf-beginn" class="cal-label" x-text="form.ganztaegig ? 'Von *' : 'Beginn *'"></label>
                            <input id="tf-beginn" :type="form.ganztaegig ? 'date' : 'datetime-local'"
                                   name="beginn" x-model="form.beginn" @change="beginnGeaendert()"
                                   class="cal-input" required>
                        </div>
                        <div>
                            <label for="tf-ende" class="cal-label" x-text="form.ganztaegig ? 'Bis einschließlich *' : 'Ende *'"></label>
                            <input id="tf-ende" :type="form.ganztaegig ? 'date' : 'datetime-local'"
                                   name="ende" x-model="form.ende" :min="form.beginn" @change="raumPruefen()"
                                   class="cal-input" required>
                        </div>
                    </div>
                </div>

                {{-- Ort --}}
                <div>
                    <label for="tf-ort" class="cal-label">Ort</label>
                    <input id="tf-ort" type="text" name="ort" x-model="form.ort" class="cal-input" maxlength="255"
                           :placeholder="form.raumBuchen && raumName(form.room_id) ? raumName(form.room_id) + ' (aus Raumbuchung)' : ''">
                </div>

                {{-- Raumbuchung --}}
                <template x-if="canBookRooms">
                    <div class="p-3 border border-teal-200 bg-teal-50/60 rounded-lg space-y-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" x-model="form.raumBuchen" @change="raumPruefen()"
                                   :disabled="!raumMoeglich"
                                   class="w-4 h-4 accent-teal-600">
                            <span class="text-sm text-gray-800"><i class="fas fa-door-open text-teal-700 mr-1"></i>Raum buchen</span>
                        </label>
                        <p x-show="!raumMoeglich" class="text-xs text-gray-600" x-text="raumHinweis"></p>

                        <div x-show="form.raumBuchen && raumMoeglich" x-cloak>
                            <label for="tf-raum" class="sr-only">Raum</label>
                            <select id="tf-raum" name="room_id" x-model="form.room_id"
                                    :disabled="!form.raumBuchen || !raumMoeglich"
                                    class="cal-input">
                                <option value="">Raum wählen…</option>
                                <template x-for="r in raumVerfuegbarkeit" :key="r.id">
                                    <option :value="String(r.id)" :disabled="!r.frei"
                                            :selected="String(r.id) === String(form.room_id)"
                                            x-text="r.name + (r.nummer ? ' (' + r.nummer + ')' : '') + (r.frei ? '' : ' – belegt: ' + r.belegt_durch)"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs" :class="raumStatus.klasse" x-text="raumStatus.text"></p>
                        </div>
                    </div>
                </template>

                {{-- Beschreibung --}}
                <div>
                    <label for="tf-beschreibung" class="cal-label">Beschreibung</label>
                    <textarea id="tf-beschreibung" name="beschreibung" x-model="form.beschreibung" rows="3"
                              class="cal-input" maxlength="5000"></textarea>
                </div>

                {{-- Wiederholung (RRULE) --}}
                <div>
                    <label for="tf-wdh" class="cal-label">Wiederholung</label>
                    <select id="tf-wdh" x-model="recurrence.type" @change="updateRrule(); raumPruefen()" class="cal-input">
                        <template x-if="originalRrule">
                            <option value="keep" x-text="'Unverändert: ' + rruleHuman(originalRrule)"></option>
                        </template>
                        <option value="none">Keine</option>
                        <option value="daily">Täglich</option>
                        <option value="weekly">Wöchentlich</option>
                        <option value="monthly">Monatlich</option>
                        <option value="custom">Benutzerdefiniert…</option>
                    </select>

                    <div x-show="recurrence.type === 'custom'" x-cloak class="mt-2 space-y-2 p-3 bg-gray-50 border border-gray-200 rounded-md">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span>Alle</span>
                            <input type="number" x-model.number="recurrence.interval" min="1" max="99"
                                   class="w-16 border border-gray-300 rounded px-2 py-1 text-sm" @change="updateRrule()">
                            <select x-model="recurrence.frequency" class="border border-gray-300 rounded px-2 py-1 text-sm bg-white" @change="updateRrule()">
                                <option value="DAILY">Tag(e)</option>
                                <option value="WEEKLY">Woche(n)</option>
                                <option value="MONTHLY">Monat(e)</option>
                                <option value="YEARLY">Jahr(e)</option>
                            </select>
                        </div>

                        <div x-show="recurrence.frequency === 'WEEKLY'" class="flex gap-1.5 flex-wrap">
                            <template x-for="day in ['MO','DI','MI','DO','FR','SA','SO']" :key="day">
                                <label class="inline-flex items-center justify-center w-9 h-8 text-xs font-medium border rounded cursor-pointer select-none"
                                       :class="recurrence.byDay.includes(dayMap[day]) ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-100'">
                                    <input type="checkbox" class="sr-only"
                                           :checked="recurrence.byDay.includes(dayMap[day])"
                                           @change="toggleDay(dayMap[day]); updateRrule()">
                                    <span x-text="day"></span>
                                </label>
                            </template>
                        </div>

                        <div class="space-y-1 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="radio" x-model="recurrence.endType" value="never" @change="updateRrule()"> Endet nie
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" x-model="recurrence.endType" value="until" @change="updateRrule()"> Am
                                <input type="date" x-model="recurrence.until" @change="updateRrule()"
                                       class="border border-gray-300 rounded px-2 py-1 text-sm" :disabled="recurrence.endType !== 'until'">
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" x-model="recurrence.endType" value="count" @change="updateRrule()"> Nach
                                <input type="number" x-model.number="recurrence.count" min="1" max="999" @change="updateRrule()"
                                       class="w-16 border border-gray-300 rounded px-2 py-1 text-sm" :disabled="recurrence.endType !== 'count'">
                                Terminen
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fuß --}}
            <div class="flex items-center justify-between gap-2 px-6 py-3 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                <p class="text-xs text-gray-500">
                    <i class="fas fa-sync-alt mr-1"></i>Wird direkt in OX gespeichert.
                </p>
                <div class="flex gap-2">
                    <button type="button" @click="schliessen()"
                            class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 rounded-md">
                        Abbrechen
                    </button>
                    <button type="submit" :disabled="sendet || form.kalender_ids.length === 0"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md">
                        <span x-show="!sendet">Speichern</span>
                        <span x-show="sendet" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i>Speichert…</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
