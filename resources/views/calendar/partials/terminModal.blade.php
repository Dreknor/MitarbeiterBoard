{{-- Termin-Detail-Modal --}}
<div x-show="showModal"
     x-cloak
     @keydown.escape.window="showModal && closeModal()"
     class="cal-modal-backdrop"
     @click.self="closeModal()">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop
         role="dialog" aria-modal="true">
        <template x-if="selectedEvent">
            <div>
                {{-- Farbbalken der Kalender --}}
                <div class="flex h-1.5 rounded-t-xl overflow-hidden">
                    <template x-for="k in (selectedEvent.verbund && selectedEvent.verbund.length ? selectedEvent.verbund : [selectedEvent.kalender])" :key="k.id">
                        <span class="flex-1" :style="'background-color: ' + k.farbe"></span>
                    </template>
                </div>

                <div class="px-6 pt-4 pb-5">
                    {{-- Kopf --}}
                    <div class="flex justify-between items-start gap-4 mb-4">
                        <div class="min-w-0">
                            <p x-show="selectedEvent.istRaum" class="text-xs font-semibold uppercase tracking-wide text-teal-700 mb-0.5">
                                <i class="fas fa-door-open mr-1"></i>Raumbelegung
                            </p>
                            <h2 class="text-lg font-semibold text-gray-900 break-words" x-text="selectedEvent.titel"></h2>
                        </div>
                        <button type="button" @click="closeModal()"
                                class="shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                aria-label="Schließen">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    {{-- Details --}}
                    <dl class="space-y-2.5 text-sm text-gray-700">
                        <div class="flex gap-3">
                            <dt class="w-5 shrink-0 text-center text-gray-400"><i class="far fa-clock"></i></dt>
                            <dd>
                                <span x-text="selectedEvent.beginn"></span>
                                <template x-if="selectedEvent.ende && selectedEvent.ende !== selectedEvent.beginn">
                                    <span> – <span x-text="selectedEvent.ende"></span></span>
                                </template>
                                <span x-show="selectedEvent.ganztaegig" class="ml-1 text-xs text-gray-500">(ganztägig)</span>
                            </dd>
                        </div>

                        <template x-if="selectedEvent.rrule">
                            <div class="flex gap-3">
                                <dt class="w-5 shrink-0 text-center text-gray-400"><i class="fas fa-redo"></i></dt>
                                <dd x-text="rruleHuman(selectedEvent.rrule)"></dd>
                            </div>
                        </template>

                        <template x-if="selectedEvent.ort">
                            <div class="flex gap-3">
                                <dt class="w-5 shrink-0 text-center text-gray-400"><i class="fas fa-map-marker-alt"></i></dt>
                                <dd class="break-words" x-text="selectedEvent.ort"></dd>
                            </div>
                        </template>

                        <template x-if="selectedEvent.raum">
                            <div class="flex gap-3">
                                <dt class="w-5 shrink-0 text-center text-teal-600"><i class="fas fa-door-open"></i></dt>
                                <dd>
                                    Raum <strong x-text="selectedEvent.raum.name"></strong> gebucht
                                    <span class="text-gray-500" x-text="'(' + selectedEvent.raum.zeit + ')'"></span>
                                </dd>
                            </div>
                        </template>

                        {{-- Raumbelegung: Zusatzinfos --}}
                        <template x-if="selectedEvent.istRaum">
                            <div class="space-y-2.5">
                                <div class="flex gap-3" x-show="selectedEvent.klassen">
                                    <dt class="w-5 shrink-0 text-center text-gray-400"><i class="fas fa-users"></i></dt>
                                    <dd x-text="'Klasse(n): ' + selectedEvent.klassen"></dd>
                                </div>
                                <div class="flex gap-3" x-show="selectedEvent.lehrer">
                                    <dt class="w-5 shrink-0 text-center text-gray-400"><i class="fas fa-chalkboard-teacher"></i></dt>
                                    <dd x-text="selectedEvent.lehrer"></dd>
                                </div>
                                <div class="flex gap-3" x-show="selectedEvent.gebuchtVon">
                                    <dt class="w-5 shrink-0 text-center text-gray-400"><i class="fas fa-user"></i></dt>
                                    <dd x-text="'Gebucht von ' + selectedEvent.gebuchtVon"></dd>
                                </div>
                            </div>
                        </template>

                        {{-- Kalender --}}
                        <template x-if="!selectedEvent.istRaum">
                            <div class="flex gap-3">
                                <dt class="w-5 shrink-0 text-center text-gray-400"><i class="far fa-calendar"></i></dt>
                                <dd class="flex flex-wrap gap-1.5">
                                    <template x-for="k in (selectedEvent.verbund && selectedEvent.verbund.length ? selectedEvent.verbund : [selectedEvent.kalender])" :key="k.id">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-gray-100 rounded-full text-xs text-gray-700">
                                            <span class="w-2 h-2 rounded-full" :style="'background-color: ' + k.farbe"></span>
                                            <span x-text="k.name"></span>
                                        </span>
                                    </template>
                                </dd>
                            </div>
                        </template>

                        <template x-if="selectedEvent.status && selectedEvent.status !== 'CONFIRMED'">
                            <div class="flex gap-3">
                                <dt class="w-5 shrink-0 text-center text-gray-400"><i class="fas fa-info-circle"></i></dt>
                                <dd x-text="statusText(selectedEvent.status)"></dd>
                            </div>
                        </template>
                    </dl>

                    {{-- Beschreibung --}}
                    <template x-if="selectedEvent.beschreibung">
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700 mb-1">Beschreibung</h3>
                            <p class="text-sm text-gray-600 whitespace-pre-line break-words" x-text="selectedEvent.beschreibung"></p>
                        </div>
                    </template>

                    {{-- Teilnehmer --}}
                    <template x-if="selectedEvent.teilnehmer && selectedEvent.teilnehmer.length > 0">
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Teilnehmer</h3>
                            <ul class="space-y-1">
                                <template x-for="t in selectedEvent.teilnehmer" :key="t.email">
                                    <li class="text-sm flex items-center gap-2">
                                        <i class="fas w-4 text-center"
                                           :class="t.status === 'ACCEPTED' ? 'fa-check-circle text-green-600'
                                                 : t.status === 'DECLINED' ? 'fa-times-circle text-red-500'
                                                 : t.status === 'TENTATIVE' ? 'fa-question-circle text-amber-500'
                                                 : 'fa-hourglass-half text-gray-400'"></i>
                                        <span x-text="t.name || t.email" class="text-gray-700"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    {{-- Aktionen --}}
                    <div class="mt-5 pt-4 border-t border-gray-200 flex flex-wrap items-center gap-2">
                        <template x-if="selectedEvent.istRaum && selectedEvent.raumUrl">
                            <a :href="selectedEvent.raumUrl"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 hover:text-gray-900 text-sm rounded-md">
                                <i class="fas fa-external-link-alt text-xs"></i> Zur Raumplanung
                            </a>
                        </template>

                        <template x-if="selectedEvent.can_edit">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="editEvent(selectedEvent)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md">
                                    <i class="fas fa-pen text-xs"></i> Bearbeiten
                                </button>
                                <button type="button" @click="deleteEvent(selectedEvent, false)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-red-300 text-red-700 hover:bg-red-50 hover:text-red-800 text-sm font-medium rounded-md">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                    <span x-text="(selectedEvent.verbund || []).length > 1 ? 'Überall löschen' : 'Löschen'"></span>
                                </button>
                                <template x-if="(selectedEvent.verbund || []).length > 1">
                                    <button type="button" @click="deleteEvent(selectedEvent, true)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-red-700 hover:bg-red-50 hover:text-red-800 text-sm rounded-md"
                                            :title="'Nur aus „' + selectedEvent.kalender.name + '“ entfernen'">
                                        Nur aus <span x-text="selectedEvent.kalender.name"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
