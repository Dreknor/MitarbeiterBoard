{{--
    Neue Ansicht – Spaltenverwaltung als Seitenleiste (Logik: spaltenSeite() → columnsManager()).
    Zusatzspalten der gewählten Klasse nach Spaltengruppe; anlegen, bearbeiten (Name, Gruppe, Art, Klassen), in andere Klassen
    kopieren, ab dieser Woche deaktivieren oder wieder aktivieren.
--}}
<div x-data="spaltenSeite()"
     data-klassen='@json($klassen->map(fn ($k) => ["id" => $k->id, "name" => $k->name]))'
     @keydown.escape.window="$store.diary.columnsCardOpen && schliessen()">
    <template x-if="$store.diary.columnsCardOpen">
        <div>
            <div class="pdn-drawer-backdrop" @click="schliessen()"></div>
            <aside class="pdn-drawer pdn-drawer--breit" role="dialog" aria-modal="true" aria-labelledby="pdnSpaltenTitel">
                <header>
                    <div>
                        <h2 id="pdnSpaltenTitel">Spalten verwalten</h2>
                        <span class="pdn-label-sm" x-text="'Klasse ' + ($store.diary.klassen.find(k => k.id == $store.diary.selectedKlasseId)?.name || '')"></span>
                    </div>
                    <button type="button" class="pdn-iconbtn" @click="schliessen()" aria-label="Schließen">✕</button>
                </header>

                <div class="pdn-drawer__body">
                    <div x-show="columnsFeedback" x-cloak role="status" class="pdn-meldung" style="border-radius:8px"
                         :class="columnsFeedbackType === 'danger' || columnsFeedbackType === 'warning' ? 'pdn-meldung--danger' : 'pdn-meldung--info'">
                        <span x-text="columnsFeedback"></span>
                    </div>

                    {{-- Vorhandene Spalten --}}
                    <section class="pdn-sblock" aria-labelledby="pdnSpaltenListe">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                            <span class="pdn-k" id="pdnSpaltenListe">Vorhandene Spalten</span>
                            <label class="pdn-switch" style="font-size:.9em;gap:8px">Deaktivierte zeigen
                                <input type="checkbox" x-model="showDeactivated"><i></i>
                            </label>
                        </div>
                        <p class="pdn-label-sm" x-show="!groupedColumns.length">Für diese Klasse gibt es noch keine Zusatzspalten.</p>

                        <template x-for="[gruppe, spalten] in groupedColumns" :key="'sg' + gruppe">
                            <div class="pdn-spaltengruppe">
                                <div class="pdn-spaltengruppe__kopf" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <span x-text="gruppe === 'Unkategorisiert' ? 'Ohne Gruppe' : gruppe"></span>
                                    {{-- „Gilt bis Freitag“: für die ganze Spaltengruppe (alle Klassen), nur Ja/Nein- und Ampel-Spalten --}}
                                    <template x-if="gruppe !== 'Unkategorisiert' && darfBisWochenende">
                                        <label class="pdn-switch" style="font-size:.85em;gap:6px;font-weight:normal"
                                               title="Bestätigungen gelten ab dem Tag der Änderung bis Freitag (Ja/Nein- und Ampel-Spalten, alle Klassen)">gilt bis Freitag
                                            <input type="checkbox" :checked="gruppeNameBisWochenende(gruppe)"
                                                   @change="setGruppeBisWochenende(gruppe, $event.target.checked)"><i></i>
                                        </label>
                                    </template>
                                    <template x-if="gruppe !== 'Unkategorisiert' && !darfBisWochenende && gruppeNameBisWochenende(gruppe)">
                                        <span class="pdn-tag pdn-tag--bisfr" title="Bestätigungen gelten ab dem Tag der Änderung bis Freitag"><i class="fas fa-calendar-week" aria-hidden="true"></i> gilt bis Freitag</span>
                                    </template>
                                </div>
                                <template x-for="col in spalten" :key="'sp' + col.id">
                                    <div class="pdn-spalte" :class="col.deactivated_from && 'is-aus'">
                                        <div class="pdn-spalte__zeile">
                                            <span class="pdn-tog" style="cursor:default" x-text="col.name"></span>
                                            <span class="pdn-tag" x-text="typen[col.type] || col.type"></span>
                                            <span class="pdn-label-sm" x-show="col.deactivated_from" x-text="'deaktiviert ab ' + new Date(col.deactivated_from).toLocaleDateString('de-DE')"></span>
                                            <span class="pdn-spacer"></span>
                                            <button type="button" class="pdn-btn pdn-btn--ghost" @click="bearbeitenUmschalten(col.id)" title="Bearbeiten (Name, Gruppe, Art, Klassen)"><i class="fas fa-pen" aria-hidden="true"></i><span class="sr-only">Bearbeiten</span></button>
                                            <button type="button" class="pdn-btn pdn-btn--ghost" @click="toggleCopyPanel(col.id)" title="In andere Klassen kopieren"><i class="fas fa-copy" aria-hidden="true"></i><span class="sr-only">Kopieren</span></button>
                                            <template x-if="!col.deactivated_from">
                                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="bestaetigen = col.id" title="Ab dieser Woche deaktivieren"><i class="fas fa-eye-slash" aria-hidden="true"></i><span class="sr-only">Deaktivieren</span></button>
                                            </template>
                                            <template x-if="col.deactivated_from">
                                                <button type="button" class="pdn-btn" @click="restoreColumn(col.id)">Wieder aktivieren</button>
                                            </template>
                                        </div>

                                        {{-- Deaktivieren bestätigen --}}
                                        <div class="pdn-spalte__extra" x-show="bestaetigen === col.id" x-cloak>
                                            <p>„<span x-text="col.name"></span>“ ab dieser Woche ausblenden? Werte ab dieser Woche werden entfernt, frühere Wochen bleiben erhalten.</p>
                                            <div style="display:flex;gap:6px">
                                                <button type="button" class="pdn-btn" style="color:var(--pdn-danger)" @click="deaktivieren(col)">Deaktivieren</button>
                                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="bestaetigen = null">Abbrechen</button>
                                            </div>
                                        </div>

                                        {{-- Bearbeiten: Name, Gruppe, Art (für alle Kopien) und Klassen --}}
                                        <div class="pdn-spalte__extra" x-show="bearbeitenFuer === col.id" x-cloak>
                                            <span class="pdn-label-sm" x-show="!bearbeitung && !bearbeitungFehler">Wird geladen …</span>
                                            <p class="pdn-label-sm" style="color:var(--pdn-danger)" x-show="!bearbeitung && bearbeitungFehler" x-text="bearbeitungFehler"></p>
                                            <template x-if="bearbeitung && bearbeitung.id === col.id">
                                                <form @submit.prevent="bearbeitungSpeichern()" style="display:flex;flex-direction:column;gap:8px">
                                                    <label class="pdn-field">Name
                                                        <input type="text" x-model="bearbeitungForm.name" maxlength="50" required :disabled="bearbeitung.eigenschaften_gesperrt">
                                                    </label>
                                                    <label class="pdn-field">Spaltengruppe
                                                        <input type="text" x-model="bearbeitungForm.category" maxlength="50" :list="'pdnBearbGruppen' + col.id" placeholder="leer = ohne Gruppe" :disabled="bearbeitung.eigenschaften_gesperrt">
                                                        <datalist :id="'pdnBearbGruppen' + col.id">
                                                            <template x-for="g in bearbeitungKategorien" :key="'bg' + col.id + g"><option :value="g"></option></template>
                                                        </datalist>
                                                    </label>
                                                    <div class="pdn-field">Art
                                                        <div class="pdn-seg" role="group" aria-label="Art der Spalte">
                                                            <template x-for="[wert, label] in [['boolean', 'Ja/Nein'], ['ampel', 'Ampel'], ['text', 'Text']]" :key="'bt' + col.id + wert">
                                                                <button type="button" @click="bearbeitungForm.type = wert" :aria-pressed="bearbeitungForm.type === wert"
                                                                        :disabled="bearbeitung.eigenschaften_gesperrt || !bearbeitung.typ_aenderbar" x-text="label"></button>
                                                            </template>
                                                        </div>
                                                        <span class="pdn-label-sm" x-show="!bearbeitung.typ_aenderbar">Die Art ist fest, weil schon Werte eingetragen sind.</span>
                                                    </div>
                                                    <p class="pdn-label-sm" x-show="bearbeitung.eigenschaften_gesperrt"
                                                       x-text="'Name, Gruppe und Art betreffen ' + bearbeitung.weitere_klassen + ' weitere Klasse(n), die du nicht bearbeiten darfst.'"></p>
                                                    <fieldset class="pdn-field" style="border:0;padding:0;margin:0">
                                                        <legend style="font-size:1em;margin:0 0 4px;color:inherit">Angezeigt in Klasse(n)</legend>
                                                        <div style="display:flex;flex-direction:column;gap:2px">
                                                            <template x-for="k in bearbeitung.klassen" :key="'bk' + col.id + k.id">
                                                                <label style="display:flex;gap:6px;align-items:center">
                                                                    <input type="checkbox" :checked="bearbeitungForm.klasse_ids.includes(k.id)" @change="bearbeitungKlasseUmschalten(k.id)">
                                                                    <span x-text="k.name"></span>
                                                                    <span class="pdn-label-sm" x-show="bearbeitungKlasseHinweis(k)" x-text="bearbeitungKlasseHinweis(k)"></span>
                                                                </label>
                                                            </template>
                                                        </div>
                                                        <span class="pdn-label-sm" x-show="bearbeitung.weitere_klassen > 0" x-text="'+ ' + bearbeitung.weitere_klassen + ' weitere Klasse(n)'"></span>
                                                    </fieldset>
                                                    <p class="pdn-label-sm" style="color:var(--pdn-danger)" x-show="bearbeitungFehler" x-text="bearbeitungFehler"></p>
                                                    <div style="display:flex;gap:6px">
                                                        <button type="submit" class="pdn-btn pdn-btn--pri" :disabled="bearbeitungSpeichert || !bearbeitungForm.name.trim()">Speichern</button>
                                                        <button type="button" class="pdn-btn pdn-btn--ghost" @click="bearbeitenSchliessen()">Abbrechen</button>
                                                    </div>
                                                </form>
                                            </template>
                                        </div>

                                        {{-- In andere Klassen kopieren --}}
                                        <div class="pdn-spalte__extra" x-show="copyOpenFor === col.id" x-cloak>
                                            <span class="pdn-label-sm">In diese Klassen kopieren:</span>
                                            <div class="pdn-chips">
                                                <template x-for="k in copyTargetsFor(col)" :key="'cp' + col.id + k.id">
                                                    <label><input type="checkbox" :checked="copyTargetIds.includes(parseInt(k.id))" @change="toggleCopyTarget(k.id)"><span x-text="k.name"></span></label>
                                                </template>
                                            </div>
                                            <div style="display:flex;gap:6px">
                                                <button type="button" class="pdn-btn pdn-btn--pri" :disabled="!copyTargetIds.length" @click="copyColumn(col.id)">Kopieren</button>
                                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="toggleCopyPanel(col.id)">Abbrechen</button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </section>

                    {{-- Neue Spalte --}}
                    <form class="pdn-panelbox" @submit.prevent="addColumn()">
                        <h3>Neue Spalte</h3>
                        <label class="pdn-field">Name (kurz, steht auf dem Schalter)
                            <input type="text" x-model="newColumnName" maxlength="50" required placeholder="z. B. +, 0, -, HA, Lesen">
                        </label>
                        <div class="pdn-field">Art
                            <div class="pdn-seg" role="group" aria-label="Art der Spalte">
                                <button type="button" @click="newColumnType = 'boolean'" :aria-pressed="newColumnType === 'boolean'">Ja/Nein</button>
                                <button type="button" @click="newColumnType = 'ampel'" :aria-pressed="newColumnType === 'ampel'">Ampel</button>
                                <button type="button" @click="newColumnType = 'text'" :aria-pressed="newColumnType === 'text'">Text</button>
                            </div>
                            <span class="pdn-label-sm" x-text="{ boolean: 'Ein Klick setzt bzw. entfernt den Haken.', ampel: 'Klicks schalten durch: Ja → In Bearbeitung → Nein → leer.', text: 'Kurzes Freitextfeld je Tag.' }[newColumnType]"></span>
                        </div>
                        <label class="pdn-field">Spaltengruppe
                            <input type="text" x-model="newColumnNewCategory" maxlength="50" list="pdnNeueGruppe" placeholder="vorhandene wählen oder neue eingeben">
                            <datalist id="pdnNeueGruppe">
                                <template x-for="g in existingColumnCategories" :key="'ng' + g"><option :value="g"></option></template>
                            </datalist>
                        </label>
                        <fieldset class="pdn-field" style="border:0;padding:0;margin:0">
                            <legend style="font-size:1em;margin:0 0 4px;color:inherit">Für Klasse(n)</legend>
                            <div class="pdn-chips">
                                <template x-for="k in allKlassenList" :key="'nk' + k.id">
                                    <label><input type="checkbox" :checked="newColumnKlasseIds.includes(parseInt(k.id))" @change="toggleNewColumnKlasse(k.id)"><span x-text="k.name"></span></label>
                                </template>
                            </div>
                        </fieldset>
                        <button type="submit" class="pdn-btn pdn-btn--pri" :disabled="!newColumnName.trim() || !newColumnKlasseIds.length">Spalte anlegen</button>
                    </form>
                </div>
            </aside>
        </div>
    </template>
</div>
