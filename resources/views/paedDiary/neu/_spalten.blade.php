{{--
    Neue Ansicht – Spaltenverwaltung als Seitenleiste (Logik: spaltenSeite() → columnsManager()).
    Zusatzspalten der gewählten Klasse nach Spaltengruppe; anlegen, Gruppe ändern, in andere Klassen
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
                                <div class="pdn-spaltengruppe__kopf" x-text="gruppe === 'Unkategorisiert' ? 'Ohne Gruppe' : gruppe"></div>
                                <template x-for="col in spalten" :key="'sp' + col.id">
                                    <div class="pdn-spalte" :class="col.deactivated_from && 'is-aus'">
                                        <div class="pdn-spalte__zeile">
                                            <span class="pdn-tog" style="cursor:default" x-text="col.name"></span>
                                            <span class="pdn-tag" x-text="typen[col.type] || col.type"></span>
                                            <span class="pdn-label-sm" x-show="col.deactivated_from" x-text="'deaktiviert ab ' + new Date(col.deactivated_from).toLocaleDateString('de-DE')"></span>
                                            <span class="pdn-spacer"></span>
                                            <button type="button" class="pdn-btn pdn-btn--ghost" @click="gruppeAendern(col)" title="Spaltengruppe ändern"><i class="fas fa-folder-open" aria-hidden="true"></i><span class="sr-only">Gruppe ändern</span></button>
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

                                        {{-- Gruppe ändern --}}
                                        <form class="pdn-spalte__extra" x-show="gruppeFuer === col.id" x-cloak @submit.prevent="gruppeSpeichern(col)">
                                            <label class="pdn-field">Spaltengruppe
                                                <input type="text" x-model="gruppeNeu" maxlength="50" :list="'pdnGruppen' + col.id" placeholder="leer = ohne Gruppe">
                                                <datalist :id="'pdnGruppen' + col.id">
                                                    <template x-for="g in existingColumnCategories" :key="'dl' + col.id + g"><option :value="g"></option></template>
                                                </datalist>
                                            </label>
                                            <div style="display:flex;gap:6px">
                                                <button type="submit" class="pdn-btn pdn-btn--pri">Speichern</button>
                                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="gruppeFuer = null">Abbrechen</button>
                                            </div>
                                        </form>

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
