{{--
    Neue Ansicht – Aufgabe erstellen/bearbeiten als Seitenleiste (Logik: aufgabeSeite() → taskPanel()).
    Öffnen per Event: pdn-aufgabe-neu (optional { stuId }) bzw. pdn-aufgabe-bearbeiten (Aufgabe).
--}}
<div x-data="aufgabeSeite()"
     @pdn-aufgabe-neu.window="openCreateModal($event.detail)"
     @pdn-aufgabe-bearbeiten.window="openEditModal($event.detail)"
     @keydown.escape.window="offen && schliessen()">
    <template x-if="offen">
        <div>
            <div class="pdn-drawer-backdrop" @click="schliessen()"></div>
            <aside class="pdn-drawer" role="dialog" aria-modal="true" aria-labelledby="pdnAufgabeTitel">
                <header>
                    <h2 id="pdnAufgabeTitel" x-text="editingTaskId ? 'Aufgabe bearbeiten' : 'Aufgabe erstellen'"></h2>
                    <button type="button" class="pdn-iconbtn" @click="schliessen()" aria-label="Schließen">✕</button>
                </header>
                <form id="pdnAufgabeForm" @submit.prevent="saveTask()">
                    <div x-show="fehler" x-cloak role="alert" class="pdn-meldung pdn-meldung--danger" style="border-radius:8px">
                        <span x-text="fehler"></span>
                    </div>

                    <label class="pdn-field">Aufgabe
                        <input type="text" x-ref="aufgabeTitel" x-model="taskFormTitle" maxlength="100" required
                               placeholder="z. B. Lesetagebuch abgeben, Elterngespräch vorbereiten">
                    </label>
                    <label class="pdn-field">Beschreibung (optional)
                        <textarea x-model="taskFormDescription" style="min-height:80px"></textarea>
                    </label>

                    <div class="pdn-field">Fällig am
                        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                            <input type="date" x-model="taskFormDueDate" aria-label="Fälligkeitsdatum">
                            <button type="button" class="pdn-btn" @click="faellig('morgen')">Morgen</button>
                            <button type="button" class="pdn-btn" @click="faellig('montag')">Nächster Montag</button>
                            <button type="button" class="pdn-btn" @click="faellig('zweiWochen')">In 2 Wochen</button>
                            <button type="button" class="pdn-btn pdn-btn--ghost" x-show="taskFormDueDate" @click="taskFormDueDate = ''">Ohne Datum</button>
                        </div>
                    </div>

                    <label class="pdn-switch" style="justify-content:flex-start;gap:12px">
                        <input type="checkbox" x-model="taskFormHighlighted"><i></i>
                        <span>Wichtig – in der Woche rot markieren</span>
                    </label>

                    <fieldset class="pdn-field" style="border:0;padding:0;margin:0" x-show="!editingTaskId">
                        <legend style="font-size:1em;margin:0 0 4px;color:inherit">Für Schüler <span class="pdn-label-sm">(je Schüler entsteht eine eigene Aufgabe)</span></legend>
                        <template x-for="klasse in $store.diary.klassen" :key="'ak' + klasse.id">
                            <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:8px">
                                <label class="pdn-check">
                                    <input type="checkbox" :checked="alleDerKlasseGewaehlt(klasse.id)" @change="klasseUmschalten(klasse.id)">
                                    <span><b x-text="klasse.name"></b> – alle</span>
                                </label>
                                <div class="pdn-chips">
                                    <template x-for="stu in schuelerDerKlasse(klasse.id)" :key="'as' + stu.id">
                                        <label><input type="checkbox" :value="String(stu.id)" x-model="taskFormSchuelerIds"><span x-text="stu.name"></span></label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </fieldset>
                    <p class="pdn-hinweis" x-show="editingTaskId"
                       x-text="'Für ' + ($store.diary.schueler.find(s => String(s.id) === taskFormSchuelerIds[0])?.name || 'den Schüler')"></p>
                </form>
                <footer>
                    <button type="submit" form="pdnAufgabeForm" class="pdn-btn pdn-btn--pri" :disabled="taskSaving || !taskFormTitle.trim()">
                        <i class="fas fa-spinner fa-spin" x-show="taskSaving" aria-hidden="true"></i>
                        <span x-text="editingTaskId ? 'Speichern' : (taskFormSchuelerIds.length > 1 ? taskFormSchuelerIds.length + ' Aufgaben anlegen' : 'Aufgabe anlegen')"></span>
                    </button>
                    <button type="button" class="pdn-btn" x-show="editingTaskId" @click="closeTask(editingTaskId); schliessen()">Erledigt</button>
                    <button type="button" class="pdn-btn pdn-btn--ghost" @click="schliessen()">Abbrechen</button>
                </footer>
            </aside>
        </div>
    </template>
</div>
