{{--
    Elterngespräche der Schülerakte (bisherige und neue Ansicht): Liste und Editor als Seitenleiste.
    Logik: resources/js/paed-diary-v2/elterngespraeche.js (Autospeichern, Konflikte, Übernahmen).
    Erwartet $elterngespraecheKonfig (PaedDiaryController::schuelerView). Muss in einem .pdn-Wrapper liegen.
--}}
<div class="pdn-eg" x-data="elterngespraecheAkte(@js($elterngespraecheKonfig))"
     @elterngespraech-oeffnen.window="oeffnenNachId($event.detail)"
     @elterngespraech-neu.window="neu()"
     @keydown.escape.window="offen && schliessen()">

    {{-- ── Liste ── --}}
    <div class="pdn-eg-kopf">
        <p class="pdn-hinweis">Gespräche planen, vorbereiten und protokollieren. Inhalte werden automatisch gespeichert.</p>
        @if($elterngespraecheKonfig['kannAnlegen'])
            <button type="button" class="pdn-btn pdn-btn--pri" @click="neu()"><i class="fas fa-plus" aria-hidden="true"></i> Elterngespräch planen</button>
        @endif
    </div>

    <div x-show="$store.elterngespraeche.fehler" x-cloak role="alert" class="pdn-meldung pdn-meldung--danger" style="border-radius:8px">
        <span x-text="$store.elterngespraeche.fehler"></span>
    </div>
    <p class="pdn-leer" x-show="$store.elterngespraeche.geladen && !liste.length" x-cloak>Noch keine Elterngespräche erfasst.</p>

    <template x-for="gruppe in [{ titel: 'Anstehend', eintraege: kommende }, { titel: 'Vergangen und abgeschlossen', eintraege: vergangene }]" :key="gruppe.titel">
        <section class="pdn-eg-gruppe" x-show="gruppe.eintraege.length">
            <span class="pdn-k" x-text="gruppe.titel"></span>
            <template x-for="g in gruppe.eintraege" :key="'eg-' + g.id">
                <button type="button" class="pdn-eg-karte" :class="{ 'is-abgesagt': g.status === 'abgesagt' }" @click="oeffnen(g)">
                    <span class="pdn-eg-datum">
                        <b x-text="datum(g.date)"></b>
                        <span x-text="zeit(g)"></span>
                    </span>
                    <span class="pdn-eg-inhalt">
                        <span class="pdn-eg-anlass" x-text="g.reason || 'Ohne Anlass'"></span>
                        <span class="pdn-meta">
                            <span class="pdn-eg-status" :class="statusKlasse(g)" x-text="statusLabel(g)"></span>
                            <span class="pdn-tag pdn-tag--conf" x-show="g.confidential">Vertraulich</span>
                            <span x-show="g.agreements.length" x-text="g.agreements.length + (g.agreements.length === 1 ? ' Vereinbarung' : ' Vereinbarungen')"></span>
                            <span x-text="g.form_label + (g.location ? ' · ' + g.location : '')"></span>
                            <span x-text="g.created_by ? '· ' + g.created_by.name : ''"></span>
                        </span>
                    </span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>
            </template>
        </section>
    </template>

    {{-- ── Editor ── --}}
    <template x-if="offen && form">
        <div>
            <div class="pdn-drawer-backdrop" @click="schliessen()"></div>
            <aside class="pdn-drawer pdn-drawer--breit pdn-eg-drawer" :class="{ 'is-voll': vollbild }" role="dialog" aria-modal="true" aria-labelledby="pdnEgTitel">
                <header>
                    <div style="min-width:0">
                        <h2 id="pdnEgTitel" x-text="titel"></h2>
                        <div class="pdn-eg-speicher" :class="'is-' + (status || 'leer')" role="status" aria-live="polite">
                            <i class="fas" :class="{ 'fa-spinner fa-spin': status === 'speichert', 'fa-check': status === 'gespeichert', 'fa-exclamation-triangle': status === 'fehler' || status === 'konflikt', 'fa-pen': status === '' && dirty }" aria-hidden="true"></i>
                            <span x-text="statusText"></span>
                            <button type="button" class="pdn-eg-link" x-show="status === 'fehler'" @click="nochmalVersuchen()">Erneut versuchen</button>
                        </div>
                    </div>
                    <span style="display:flex;gap:6px;flex:none">
                        <button type="button" class="pdn-btn pdn-btn--ghost" @click="vollbildUmschalten()" :aria-pressed="vollbild"
                                :title="vollbild ? 'Als Seitenleiste anzeigen' : 'Ganzes Fenster nutzen'">
                            <i class="fas" :class="vollbild ? 'fa-compress' : 'fa-expand'" aria-hidden="true"></i>
                            <span class="pdn-btn-text" x-text="vollbild ? 'Seitenleiste' : 'Ganzes Fenster'"></span>
                        </button>
                        <button type="button" class="pdn-iconbtn" @click="schliessen()" aria-label="Schließen">✕</button>
                    </span>
                </header>

                <div class="pdn-meldung pdn-meldung--danger" x-show="status === 'konflikt'" role="alert">
                    <div>
                        <b x-text="statusText"></b>
                        <p>Deine Änderungen sind noch nicht gespeichert.</p>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                            <button type="button" class="pdn-btn" @click="konfliktNeuLaden()">Neu laden (meine Änderungen verwerfen)</button>
                            <button type="button" class="pdn-btn pdn-btn--pri" @click="konfliktBehalten()">Meine Fassung behalten</button>
                        </div>
                    </div>
                </div>
                <div class="pdn-meldung pdn-meldung--danger" x-show="meldung" role="alert">
                    <span x-text="meldung"></span><button type="button" @click="meldung = ''" aria-label="Hinweis schließen">×</button>
                </div>
                <div class="pdn-meldung pdn-meldung--info" x-show="form.id && form.status !== 'geplant'">
                    <span x-text="form.status === 'abgeschlossen' ? 'Protokoll abgeschlossen' + (form.completed_by ? ' von ' + form.completed_by : '') + ' – nur noch lesbar.' : 'Gespräch abgesagt.'"></span>
                </div>

                <div class="pdn-seg pdn-eg-abschnitte" role="tablist" aria-label="Abschnitte">
                    <button type="button" role="tab" :aria-pressed="abschnitt === 'termin'" @click="abschnittWechseln('termin')">1 · Termin</button>
                    <button type="button" role="tab" :aria-pressed="abschnitt === 'vorbereitung'" @click="abschnittWechseln('vorbereitung')">2 · Vorbereitung</button>
                    <button type="button" role="tab" :aria-pressed="abschnitt === 'protokoll'" @click="abschnittWechseln('protokoll')">3 · Protokoll</button>
                </div>

                <form class="pdn-drawer__body" @submit.prevent @input="aendern()" @change="aendern()" @focusout="sofortSpeichern()">
                    <fieldset :disabled="nurLesen" class="pdn-eg-fieldset">

                        {{-- 1 · Termin & Teilnehmende --}}
                        <div x-show="abschnitt === 'termin'" class="pdn-eg-abschnitt">
                            <div class="pdn-eg-reihe">
                                <label class="pdn-field">Datum <input type="date" x-model="form.date" required></label>
                                <label class="pdn-field">Beginn <input type="time" x-model="form.start_time"></label>
                                <label class="pdn-field">Ende <input type="time" x-model="form.end_time"></label>
                            </div>
                            <div class="pdn-eg-reihe">
                                <label class="pdn-field">Form
                                    <select x-model="form.form">
                                        <template x-for="(label, wert) in formen" :key="'f-' + wert"><option :value="wert" x-text="label"></option></template>
                                    </select>
                                </label>
                                <label class="pdn-field" style="flex:2">Ort / Raum <input type="text" x-model="form.location" maxlength="255" placeholder="z. B. Raum 104"></label>
                            </div>

                            <div class="pdn-field">
                                <span>Kolleg:innen</span>
                                <div class="pdn-chips">
                                    <template x-for="id in form.participant_ids" :key="'p-' + id">
                                        <span class="pdn-eg-chip"><span x-text="kollegeName(id)"></span>
                                            <button type="button" @click="kollegeEntfernen(id)" :aria-label="'Entfernen: ' + kollegeName(id)" x-show="!nurLesen">×</button>
                                        </span>
                                    </template>
                                </div>
                                <input type="text" x-model="kollegenSuche" placeholder="Name suchen …" aria-label="Kolleg:in suchen" x-show="!nurLesen" @input.stop @change.stop>
                                <div class="pdn-chips" x-show="!nurLesen">
                                    <template x-for="k in kollegenTreffer" :key="'k-' + k.id">
                                        <button type="button" class="pdn-eg-vorschlag" @click="kollegeHinzufuegen(k.id)">
                                            <span x-text="'+ ' + k.name"></span><small x-show="k.class"> · Klasse</small>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <label class="pdn-field">Eltern / Sorgeberechtigte
                                <input type="text" x-model="form.parents" maxlength="1000" placeholder="z. B. Frau und Herr Muster">
                            </label>
                            <label class="pdn-field">Weitere Personen
                                <input type="text" x-model="form.other_participants" maxlength="1000" placeholder="z. B. Schulsozialarbeit, Dolmetscherin">
                            </label>
                            <label class="pdn-check"><input type="checkbox" x-model="form.student_present"><span>Schüler:in nimmt teil</span></label>
                            <label class="pdn-check"><input type="checkbox" x-model="form.confidential"><span class="pdn-lock">Vertraulich: nur für mich, eingetragene Kolleg:innen und Berechtigte sichtbar</span></label>

                            <div class="pdn-eg-kasten">
                                <label class="pdn-check"><input type="checkbox" x-model="form.calendar"><span>Termin im Tagebuch-Kalender eintragen</span></label>
                                <label class="pdn-check" x-show="form.calendar"><input type="checkbox" x-model="form.pause_entries"><span>Offene Notizen an diesem Tag pausieren</span></label>
                                <p class="pdn-hinweis">Der Kalendertermin enthält nur Titel und Uhrzeit, keine Gesprächsinhalte.</p>
                            </div>
                        </div>

                        {{-- 2 · Vorbereitung --}}
                        <div x-show="abschnitt === 'vorbereitung'" class="pdn-eg-abschnitt">
                            <label class="pdn-field">Anlass
                                <textarea x-model="form.reason" maxlength="5000" style="min-height:70px" placeholder="Worum geht es?"></textarea>
                            </label>

                            <div class="pdn-eg-zeile">
                                <span class="pdn-k">Gesprächspunkte</span>
                                <span class="pdn-spacer"></span>
                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="akteUmschalten()" x-show="!nurLesen">
                                    <i class="fas fa-folder-open" aria-hidden="true"></i> Aus der Akte übernehmen
                                </button>
                            </div>

                            <div class="pdn-eg-akte" x-show="akteOffen" x-cloak>
                                <p x-show="akteLaedt" class="pdn-hinweis"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Akte wird geladen …</p>
                                <template x-if="akte">
                                    <div>
                                        <template x-if="akte.ziel">
                                            <button type="button" class="pdn-eg-akteintrag" @click="ausZiel(akte.ziel)"><b>Aktuelles Ziel</b> <span x-text="kurz(akte.ziel.goal_text, 120)"></span></button>
                                        </template>
                                        <template x-for="t in akte.aufgaben" :key="'aa-' + t.id">
                                            <button type="button" class="pdn-eg-akteintrag" @click="ausAufgabe(t)"><b>Aufgabe</b> <span x-text="kurz(t.title, 120)"></span></button>
                                        </template>
                                        <template x-for="e in akte.eintraege" :key="'ae-' + e.id">
                                            <button type="button" class="pdn-eg-akteintrag" @click="ausEintrag(e)"><b x-text="e.formatted_date"></b> <span x-text="kurz(e.content, 120)"></span></button>
                                        </template>
                                        <p class="pdn-hinweis" x-show="!akte.ziel && !akte.aufgaben.length && !akte.eintraege.length">In den letzten 60 Tagen gibt es keine Einträge.</p>
                                    </div>
                                </template>
                            </div>

                            <template x-for="(p, i) in form.topics" :key="p.key">
                                <div class="pdn-eg-punkt">
                                    <div class="pdn-eg-zeile">
                                        <b class="pdn-eg-nr" x-text="(i + 1) + '.'"></b>
                                        <input type="text" x-model="p.title" maxlength="500" placeholder="Thema" :aria-label="'Thema ' + (i + 1)" style="flex:1">
                                        <span x-show="!nurLesen" style="display:flex;gap:4px">
                                            <button type="button" class="pdn-iconbtn" @click="punktVerschieben(i, -1)" aria-label="Nach oben">↑</button>
                                            <button type="button" class="pdn-iconbtn" @click="punktVerschieben(i, 1)" aria-label="Nach unten">↓</button>
                                            <button type="button" class="pdn-iconbtn" @click="punktEntfernen(i)" aria-label="Punkt entfernen">✕</button>
                                        </span>
                                    </div>
                                    <textarea x-model="p.preparation" maxlength="5000" :placeholder="'Vorbereitung (' + vorbereitungHinweis + ')'" :aria-label="'Vorbereitung zu Punkt ' + (i + 1)"></textarea>
                                </div>
                            </template>
                            <button type="button" class="pdn-card-add" @click="punktHinzufuegen()" x-show="!nurLesen">+ Gesprächspunkt</button>

                            <label class="pdn-field">Notizen zur Vorbereitung
                                <textarea x-model="form.preparation_notes" maxlength="10000" style="min-height:80px" :placeholder="'Allgemeine Vorbereitung (' + vorbereitungHinweis + ')'"></textarea>
                            </label>

                            <div class="pdn-eg-kasten">
                                <label class="pdn-check"><input type="checkbox" x-model="form.preparation_in_protocol"><span>Vorbereitung ins Protokoll übernehmen</span></label>
                                <p class="pdn-hinweis">Ist der Schalter an, erscheinen die Notizen zur Vorbereitung und die Vorbereitung je Gesprächspunkt im Protokoll-PDF. Sonst bleiben sie intern.</p>
                            </div>
                        </div>

                        {{-- 3 · Protokoll --}}
                        <div x-show="abschnitt === 'protokoll'" class="pdn-eg-abschnitt">
                            <p class="pdn-hinweis" x-show="!form.topics.length">Noch keine Gesprächspunkte – lege sie unter „Vorbereitung“ an oder notiere den Verlauf unten.</p>
                            <template x-for="(p, i) in form.topics" :key="'r-' + p.key">
                                <div class="pdn-eg-punkt pdn-eg-punkt--protokoll">
                                    <div class="pdn-eg-punkt__vorbereitung">
                                        <b x-text="(i + 1) + '. ' + (p.title || 'Ohne Thema')"></b>
                                        <p class="pdn-eg-intern" x-show="p.preparation" x-text="p.preparation" :title="'Vorbereitung – ' + vorbereitungHinweis"></p>
                                    </div>
                                    <textarea x-model="p.result" maxlength="10000" placeholder="Ergebnis" :aria-label="'Ergebnis zu Punkt ' + (i + 1)"></textarea>
                                </div>
                            </template>

                            <label class="pdn-field">Gesprächsverlauf / Sonstiges
                                <textarea x-model="form.notes" maxlength="20000"></textarea>
                            </label>

                            <span class="pdn-k">Vereinbarungen</span>
                            <template x-for="(a, i) in form.agreements" :key="a.key">
                                <div class="pdn-eg-punkt">
                                    <div class="pdn-eg-zeile">
                                        <b class="pdn-eg-nr" x-text="(i + 1) + '.'"></b>
                                        <textarea x-model="a.text" maxlength="2000" placeholder="Was wurde vereinbart?" style="min-height:52px;flex:1" :aria-label="'Vereinbarung ' + (i + 1)"></textarea>
                                        <button type="button" class="pdn-iconbtn" @click="vereinbarungEntfernen(i)" aria-label="Vereinbarung entfernen" x-show="!nurLesen">✕</button>
                                    </div>
                                    <div class="pdn-eg-reihe">
                                        <label class="pdn-field">Verantwortlich
                                            <select x-model="a.responsible">
                                                <option value="">–</option>
                                                <template x-for="(label, wert) in verantwortlich" :key="'v-' + wert"><option :value="wert" x-text="label"></option></template>
                                            </select>
                                        </label>
                                        <label class="pdn-field">bis <input type="date" x-model="a.due_date"></label>
                                    </div>
                                    <div class="pdn-eg-zeile">
                                        <span class="pdn-tag" x-show="a.task_id" style="background:var(--pdn-ok-soft);color:var(--pdn-ok)">✓ als Aufgabe übernommen</span>
                                        <span class="pdn-tag" x-show="a.goal_id" style="background:var(--pdn-ok-soft);color:var(--pdn-ok)">✓ als Ziel übernommen</span>
                                    </div>
                                </div>
                            </template>
                            <button type="button" class="pdn-card-add" @click="vereinbarungHinzufuegen()" x-show="!nurLesen">+ Vereinbarung</button>

                            <label class="pdn-field">Nächster Termin <input type="date" x-model="form.next_date"></label>
                        </div>
                    </fieldset>

                    {{-- Übernahme in die Akte geht auch nach dem Abschluss (außerhalb des gesperrten Fieldsets) --}}
                    <div x-show="abschnitt === 'protokoll' && form.agreements.some(a => a.text.trim())" class="pdn-eg-kasten">
                        <span class="pdn-k">In die Schülerakte übernehmen</span>
                        <template x-for="(a, i) in form.agreements.filter(x => x.text.trim())" :key="'u-' + a.key">
                            <div class="pdn-eg-zeile">
                                <span style="flex:1;min-width:0" x-text="kurz(a.text, 70)"></span>
                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="uebernehmen(a, 'aufgabe')" :disabled="!!a.task_id">→ Aufgabe</button>
                                <button type="button" class="pdn-btn pdn-btn--ghost" @click="uebernehmen(a, 'ziel')" :disabled="!!a.goal_id">→ Ziel</button>
                            </div>
                        </template>
                    </div>
                </form>

                <footer>
                    <template x-if="form.id && form.can.complete">
                        <button type="button" class="pdn-btn pdn-btn--pri" @click="abschliessen()" :disabled="aktionLaeuft">Protokoll abschließen</button>
                    </template>
                    <button type="button" class="pdn-btn" @click="pdfOeffnen()" x-show="form.id"><i class="fas fa-file-pdf" aria-hidden="true"></i> Protokoll-PDF</button>
                    <button type="button" class="pdn-btn" @click="speichern()" x-show="!nurLesen && dirty && status !== 'speichert'">Jetzt speichern</button>
                    <button type="button" class="pdn-btn pdn-btn--ghost" @click="folgegespraech()" x-show="form.id && kannAnlegen">Folgegespräch planen</button>
                    <span class="pdn-spacer"></span>
                    <template x-if="form.id && form.can.reopen">
                        <button type="button" class="pdn-btn pdn-btn--ghost" @click="wiederOeffnen()" :disabled="aktionLaeuft">Wieder öffnen</button>
                    </template>
                    <template x-if="form.id && form.can.cancel">
                        <button type="button" class="pdn-btn pdn-btn--ghost" @click="absagen()" :disabled="aktionLaeuft">Absagen</button>
                    </template>
                    <template x-if="form.id && form.can.delete">
                        <button type="button" class="pdn-btn pdn-btn--ghost" @click="loeschen()" :disabled="aktionLaeuft">Löschen</button>
                    </template>
                </footer>
            </aside>
        </div>
    </template>
</div>
