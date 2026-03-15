{{-- Termin-Detail-Modal --}}
<div x-show="showModal"
     x-cloak
     @keydown.escape.window="closeModal()"
     class="cal-modal-backdrop"
     @click.self="closeModal()">

    <div class="bg-white rounded-lg shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6"
         @click.stop>
        <template x-if="selectedEvent">
            <div>
                {{-- Header --}}
                <div class="flex justify-between items-start mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 pr-4" x-text="selectedEvent.titel"></h2>
                    <button @click="closeModal()"
                            class="shrink-0 text-gray-400 hover:text-gray-600 text-2xl leading-none">
                        &times;
                    </button>
                </div>

                {{-- Details --}}
                <div class="space-y-2 text-sm text-gray-700">
                    {{-- Datum/Uhrzeit --}}
                    <div class="flex items-center gap-2">
                        <span class="w-5 text-center">📅</span>
                        <span x-text="selectedEvent.beginn"></span>
                        <template x-if="!selectedEvent.ganztaegig">
                            <span>– <span x-text="selectedEvent.ende"></span></span>
                        </template>
                    </div>

                    {{-- Ort --}}
                    <template x-if="selectedEvent.ort">
                        <div class="flex items-center gap-2">
                            <span class="w-5 text-center">📍</span>
                            <span x-text="selectedEvent.ort"></span>
                        </div>
                    </template>

                    {{-- Kalender --}}
                    <div class="flex items-center gap-2">
                        <span class="w-5 text-center">📋</span>
                        <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0"
                              :style="'background-color: ' + selectedEvent.kalender.farbe"></span>
                        <span x-text="selectedEvent.kalender.name"></span>
                    </div>

                    {{-- Wiederholung --}}
                    <template x-if="selectedEvent.rrule">
                        <div class="flex items-center gap-2">
                            <span class="w-5 text-center">🔁</span>
                            <span x-text="rruleHuman(selectedEvent.rrule)"></span>
                        </div>
                    </template>

                    {{-- Status --}}
                    <template x-if="selectedEvent.status">
                        <div class="flex items-center gap-2">
                            <span class="w-5 text-center">📌</span>
                            <span x-text="selectedEvent.status"></span>
                        </div>
                    </template>
                </div>

                {{-- Beschreibung --}}
                <template x-if="selectedEvent.beschreibung">
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h3 class="text-sm font-medium text-gray-700 mb-1">Beschreibung</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line"
                           x-text="selectedEvent.beschreibung"></p>
                    </div>
                </template>

                {{-- Teilnehmer --}}
                <template x-if="selectedEvent.teilnehmer && selectedEvent.teilnehmer.length > 0">
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">👥 Teilnehmer</h3>
                        <ul class="space-y-1">
                            <template x-for="t in selectedEvent.teilnehmer" :key="t.email">
                                <li class="text-sm flex items-center gap-2">
                                    <span x-text="t.status === 'ACCEPTED'  ? '✅' :
                                                  t.status === 'DECLINED'  ? '❌' :
                                                  t.status === 'TENTATIVE' ? '❓' : '⏳'"></span>
                                    <span x-text="t.name || t.email" class="text-gray-700"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Aktions-Buttons --}}
                <template x-if="selectedEvent.can_edit">
                    <div class="mt-6 pt-4 border-t border-gray-200 flex gap-2">
                        <button @click="editEvent(selectedEvent)"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700
                                       text-white text-sm font-medium rounded-md transition-colors">
                            Bearbeiten
                        </button>
                        <button @click="deleteEvent(selectedEvent)"
                                class="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700
                                       text-white text-sm font-medium rounded-md transition-colors">
                            Löschen
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

