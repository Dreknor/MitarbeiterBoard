/**
 * Elterngespräche in der Schülerakte (bisherige und neue Ansicht).
 *
 *   - Store `elterngespraeche`: Liste der sichtbaren Gespräche (Tab, Verlauf und Seitenleiste
 *     der neuen Ansicht nutzen dieselben Daten).
 *   - elterngespraecheAkte(konfig): Liste + Editor (Termin & Teilnehmende, Vorbereitung, Protokoll).
 *
 * Autospeichern: Änderungen werden 2 s nach der letzten Eingabe gespeichert, außerdem beim
 * Verlassen eines Feldes, beim Abschnittswechsel und beim Schließen. Ein neues Gespräch wird mit
 * dem ersten Speichern angelegt (POST), danach per PUT mit `version` (409 bei fremder Änderung).
 * Bewusst keine Kopie der Inhalte in localStorage/sessionStorage: Protokolle sind vertraulich und
 * werden oft auf geteilten Geräten bearbeitet. Gemerkt wird nur, ob der Editor im Vollbild läuft.
 */

import { csrfToken } from './utils.js';

const VERZOEGERUNG_MS = 2000;
const MAX_WARTEN_MS = 60000;
const VOLLBILD_SCHLUESSEL = 'pdn-elterngespraech-vollbild';

const heuteIso = () => {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
};

const schluessel = () => Math.random().toString(36).slice(2, 10) + Date.now().toString(36);

const datumDe = (iso) => {
    if (!iso) return '';
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
};

async function anfrage(url, methode = 'GET', daten = null, keepalive = false) {
    const resp = await fetch(url, {
        method: methode,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: daten ? JSON.stringify(daten) : null,
        keepalive,
    });
    const json = await resp.json().catch(() => ({}));
    return { resp, json };
}

function fehlermeldung(json, standard) {
    const details = json?.errors ? Object.values(json.errors).flat().join(' ') : '';
    return details || json?.message || standard;
}

/** Server-Daten → bearbeitbares Formular (tiefe Kopie, mit Client-Schlüsseln für Listen). */
function alsFormular(g) {
    return {
        ...JSON.parse(JSON.stringify(g)),
        start_time: g.start_time || '',
        end_time: g.end_time || '',
        location: g.location || '',
        parents: g.parents || '',
        other_participants: g.other_participants || '',
        reason: g.reason || '',
        preparation_notes: g.preparation_notes || '',
        notes: g.notes || '',
        next_date: g.next_date || '',
        participant_ids: (g.participants || []).map(p => p.id),
        topics: (g.topics || []).map(t => ({ key: schluessel(), title: t.title || '', preparation: t.preparation || '', result: t.result || '' })),
        agreements: (g.agreements || []).map(a => ({ ...a, key: schluessel(), text: a.text || '', responsible: a.responsible || '', due_date: a.due_date || '' })),
    };
}

function leeresFormular(vorlage = {}) {
    return {
        id: null, version: null, status: 'geplant',
        date: heuteIso(), start_time: '', end_time: '', location: '', form: 'vor_ort',
        confidential: false, student_present: false, parents: '', other_participants: '',
        reason: '', topics: [], preparation_notes: '', preparation_in_protocol: false, notes: '', next_date: '',
        participant_ids: [], agreements: [], calendar: true, pause_entries: false,
        can: { update: true, complete: true, cancel: true, reopen: false, delete: true },
        ...vorlage,
    };
}

function nutzdaten(f) {
    return {
        date: f.date, start_time: f.start_time || null, end_time: f.end_time || null,
        location: f.location, form: f.form, confidential: !!f.confidential, student_present: !!f.student_present,
        parents: f.parents, other_participants: f.other_participants, reason: f.reason,
        topics: f.topics.map(t => ({ title: t.title, preparation: t.preparation, result: t.result })),
        preparation_notes: f.preparation_notes, preparation_in_protocol: !!f.preparation_in_protocol, notes: f.notes, next_date: f.next_date || null,
        participant_ids: f.participant_ids,
        agreements: f.agreements.map(a => ({ id: a.id || null, key: a.key, text: a.text, responsible: a.responsible || null, due_date: a.due_date || null })),
        calendar: !!f.calendar, pause_entries: !!f.pause_entries,
        version: f.version,
    };
}

export function registerElterngespraeche(Alpine) {
    Alpine.store('elterngespraeche', {
        liste: [],
        geladen: false,
        fehler: '',

        async laden(schuelerId) {
            this.fehler = '';
            try {
                const { resp, json } = await anfrage(`/paed-diary/schueler/${schuelerId}/gespraeche`);
                if (!resp.ok) throw new Error(fehlermeldung(json, 'Die Elterngespräche konnten nicht geladen werden.'));
                this.liste = json.data || [];
            } catch (e) {
                this.fehler = e.message;
            } finally {
                this.geladen = true;
            }
        },

        ersetzen(g) {
            const i = this.liste.findIndex(x => x.id === g.id);
            if (i >= 0) this.liste.splice(i, 1, g); else this.liste.unshift(g);
            this.liste.sort((a, b) => (b.date + (b.start_time || '')).localeCompare(a.date + (a.start_time || '')));
        },

        entfernen(id) {
            this.liste = this.liste.filter(x => x.id !== id);
        },

        /** Nächstes geplantes Gespräch ab heute */
        get naechstes() {
            const heute = heuteIso();
            return this.liste.filter(g => g.status === 'geplant' && g.date >= heute)
                .sort((a, b) => (a.date + (a.start_time || '')).localeCompare(b.date + (b.start_time || '')))[0] || null;
        },

        get offeneProtokolle() {
            return this.liste.filter(g => g.protocol_open).length;
        },
    });

    Alpine.data('elterngespraecheAkte', (konfig) => ({
        schuelerId: konfig.schuelerId,
        kollegen: konfig.kollegen || [],
        kannAnlegen: !!konfig.kannAnlegen,
        formen: konfig.formen || {},
        verantwortlich: konfig.verantwortlich || {},

        offen: false,
        vollbild: false,
        abschnitt: 'termin',
        form: null,
        kollegenSuche: '',

        // Autospeichern
        dirty: false,
        laeuft: false,
        nachlauf: false,
        timer: null,
        wartenMs: 0,
        status: '',          // '' | speichert | gespeichert | fehler | konflikt
        statusText: '',
        konflikt: null,
        aktionLaeuft: false,
        meldung: '',

        // „Aus der Akte übernehmen“
        akteOffen: false,
        akte: null,
        akteLaedt: false,

        init() {
            this.$store.elterngespraeche.laden(this.schuelerId);
            // Nur die Darstellungswahl wird im Browser gemerkt, keine Inhalte
            try { this.vollbild = localStorage.getItem(VOLLBILD_SCHLUESSEL) === '1'; } catch (e) { /* ohne Speicher */ }
            this._beforeUnload = (e) => {
                if (this.dirty || this.laeuft) { e.preventDefault(); e.returnValue = ''; }
            };
            this._pageHide = () => { if (this.dirty) this.speichern({ keepalive: true }); };
            window.addEventListener('beforeunload', this._beforeUnload);
            window.addEventListener('pagehide', this._pageHide);
        },

        destroy() {
            window.removeEventListener('beforeunload', this._beforeUnload);
            window.removeEventListener('pagehide', this._pageHide);
        },

        // ── Liste ──
        get liste() { return this.$store.elterngespraeche.liste; },
        get kommende() {
            const heute = heuteIso();
            return this.liste.filter(g => g.status === 'geplant' && g.date >= heute)
                .slice().sort((a, b) => (a.date + (a.start_time || '')).localeCompare(b.date + (b.start_time || '')));
        },
        get vergangene() {
            const kommend = new Set(this.kommende.map(g => g.id));
            return this.liste.filter(g => !kommend.has(g.id));
        },
        datum: datumDe,
        zeit(g) {
            return g.start_time ? g.start_time + (g.end_time ? '–' + g.end_time : '') + ' Uhr' : '';
        },
        statusKlasse(g) {
            if (g.protocol_open) return 'pdn-eg-status--offen';
            return { geplant: 'pdn-eg-status--geplant', abgeschlossen: 'pdn-eg-status--fertig', abgesagt: 'pdn-eg-status--abgesagt' }[g.status] || '';
        },
        statusLabel(g) {
            return g.protocol_open ? 'Protokoll offen' : g.status_label;
        },

        // ── Editor öffnen/schließen ──
        async neu(vorlage = {}) {
            if (this.offen) await this.schliessen();
            this.form = leeresFormular(vorlage);
            this.zuruecksetzen();
            this.abschnitt = 'termin';
            this.offen = true;
            if (Object.keys(vorlage).length) this.aendern();
        },

        async oeffnen(g) {
            if (this.offen && this.form?.id === g.id) return;
            if (this.offen) await this.schliessen();
            this.form = alsFormular(g);
            this.zuruecksetzen();
            this.abschnitt = g.status === 'geplant' && g.date <= heuteIso() ? 'protokoll' : (g.status === 'geplant' ? 'termin' : 'protokoll');
            this.offen = true;
        },

        oeffnenNachId(id) {
            const g = this.liste.find(x => x.id === Number(id));
            if (g) this.oeffnen(g);
        },

        async schliessen() {
            clearTimeout(this.timer);
            if (this.dirty && this.status !== 'konflikt') await this.speicherAbwarten();
            if (this.dirty && !confirm('Nicht alle Änderungen konnten gespeichert werden. Trotzdem schließen?')) return;
            this.offen = false;
            this.akteOffen = false;
            this.form = null;
            this.dirty = false;
        },

        zuruecksetzen() {
            clearTimeout(this.timer);
            this.dirty = false;
            this.status = '';
            this.statusText = this.form?.updated_at ? 'Zuletzt gespeichert' + (this.form.updated_by ? ' von ' + this.form.updated_by : '') : '';
            this.konflikt = null;
            this.meldung = '';
            this.wartenMs = 0;
            this.akteOffen = false;
        },

        /** Editor als Seitenleiste oder über das ganze Fenster (z. B. zum Mitschreiben im Gespräch) */
        vollbildUmschalten() {
            this.vollbild = !this.vollbild;
            try { localStorage.setItem(VOLLBILD_SCHLUESSEL, this.vollbild ? '1' : '0'); } catch (e) { /* ohne Speicher */ }
        },

        abschnittWechseln(name) {
            this.sofortSpeichern();
            this.abschnitt = name;
        },

        get nurLesen() {
            return !this.form || (this.form.id ? !this.form.can?.update : !this.kannAnlegen);
        },

        /** Hinweis an den Vorbereitungsfeldern, ob sie im Protokoll-PDF erscheinen */
        get vorbereitungHinweis() {
            return this.form?.preparation_in_protocol ? 'erscheint im Protokoll' : 'intern, erscheint nicht im Protokoll';
        },

        get titel() {
            if (!this.form) return '';
            return this.form.id ? 'Elterngespräch am ' + datumDe(this.form.date) : 'Elterngespräch planen';
        },

        // ── Autospeichern ──
        aendern() {
            if (this.nurLesen || this.status === 'konflikt') return;
            this.dirty = true;
            this.status = '';
            this.statusText = 'Ungespeicherte Änderungen';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.speichern(), VERZOEGERUNG_MS);
        },

        sofortSpeichern() {
            if (this.dirty) this.speichern();
        },

        async speichern(optionen = {}) {
            clearTimeout(this.timer);
            const form = this.form;
            if (!form || this.nurLesen || !this.dirty || this.status === 'konflikt') return;
            if (this.laeuft) { this.nachlauf = true; return; }
            if (!form.date) { this.status = 'fehler'; this.statusText = 'Bitte ein Datum angeben.'; return; }

            this.laeuft = true;
            this.dirty = false;
            this.status = 'speichert';
            this.statusText = 'Speichert …';
            try {
                const neu = !form.id;
                const { resp, json } = await anfrage(
                    neu ? `/paed-diary/schueler/${this.schuelerId}/gespraeche` : `/paed-diary/gespraeche/${form.id}`,
                    neu ? 'POST' : 'PUT',
                    nutzdaten(form),
                    !!optionen.keepalive
                );
                if (resp.status === 409) {
                    this.dirty = true;
                    this.konflikt = json.data;
                    this.status = 'konflikt';
                    this.statusText = json.message || 'Das Gespräch wurde zwischenzeitlich geändert.';
                    return;
                }
                if (resp.status === 422 || resp.status === 403) {
                    this.dirty = true;
                    this.status = 'fehler';
                    this.statusText = 'Nicht gespeichert: ' + fehlermeldung(json, 'Bitte die Eingaben prüfen.');
                    return;
                }
                if (!resp.ok) throw new Error(fehlermeldung(json, 'Speichern fehlgeschlagen.'));

                this.antwortUebernehmen(form, json);
                this.wartenMs = 0;
                if (!this.dirty) {
                    this.status = 'gespeichert';
                    this.statusText = 'Gespeichert ' + new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                }
            } catch (e) {
                // Netzwerk- oder Serverfehler: Eingaben bleiben, erneuter Versuch mit wachsendem Abstand
                this.dirty = true;
                this.status = 'fehler';
                this.wartenMs = Math.min(Math.max(VERZOEGERUNG_MS, this.wartenMs * 2), MAX_WARTEN_MS);
                this.statusText = 'Nicht gespeichert – neuer Versuch in ' + Math.round(this.wartenMs / 1000) + ' s';
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.speichern(), this.wartenMs);
            } finally {
                this.laeuft = false;
                if (this.nachlauf) {
                    this.nachlauf = false;
                    if (this.dirty && this.status !== 'fehler') this.speichern();
                }
            }
        },

        /** Übernimmt IDs, Version und Rechte aus der Antwort, ohne laufende Eingaben zu überschreiben. */
        antwortUebernehmen(form, json) {
            const g = json.data;
            Object.assign(form, {
                id: g.id, version: g.version, status: g.status, can: g.can, appointment_id: g.appointment_id,
                updated_at: g.updated_at, updated_by: g.updated_by, protocol_open: g.protocol_open,
                status_label: g.status_label, created_by: g.created_by,
            });
            const keys = json.agreement_keys || {};
            form.agreements.forEach(a => { if (!a.id && keys[a.key]) a.id = keys[a.key]; });
            this.$store.elterngespraeche.ersetzen(g);
        },

        /** Wartet, bis offene Änderungen gespeichert sind (für Aktionen, PDF und Schließen). */
        async speicherAbwarten() {
            if (this.dirty && this.status !== 'konflikt') await this.speichern();
            const start = Date.now();
            while ((this.laeuft || (this.dirty && this.status === '')) && Date.now() - start < 15000) {
                await new Promise(r => setTimeout(r, 100));
                if (this.dirty && !this.laeuft && this.status === '') await this.speichern();
            }
            return !this.dirty;
        },

        nochmalVersuchen() {
            this.wartenMs = 0;
            this.dirty = true;
            this.status = '';
            this.speichern();
        },

        // ── Konflikt ──
        konfliktNeuLaden() {
            this.form = alsFormular(this.konflikt);
            this.$store.elterngespraeche.ersetzen(this.konflikt);
            this.zuruecksetzen();
        },

        konfliktBehalten() {
            this.form.version = this.konflikt.version;
            this.konflikt = null;
            this.status = '';
            this.dirty = true;
            this.speichern();
        },

        // ── Listen im Formular ──
        punktHinzufuegen(vorlage = {}) {
            this.form.topics.push({ key: schluessel(), title: '', preparation: '', result: '', ...vorlage });
            this.aendern();
        },
        punktEntfernen(i) {
            this.form.topics.splice(i, 1);
            this.aendern();
        },
        punktVerschieben(i, richtung) {
            const j = i + richtung;
            if (j < 0 || j >= this.form.topics.length) return;
            const [p] = this.form.topics.splice(i, 1);
            this.form.topics.splice(j, 0, p);
            this.aendern();
        },
        vereinbarungHinzufuegen() {
            this.form.agreements.push({ id: null, key: schluessel(), text: '', responsible: '', due_date: '', task_id: null, goal_id: null });
            this.aendern();
        },
        vereinbarungEntfernen(i) {
            const a = this.form.agreements[i];
            if ((a.task_id || a.goal_id) && !confirm('Die Vereinbarung wurde bereits übernommen. Aufgabe bzw. Ziel bleiben erhalten. Vereinbarung entfernen?')) return;
            this.form.agreements.splice(i, 1);
            this.aendern();
        },

        // ── Kolleg:innen ──
        get kollegenTreffer() {
            const suche = this.kollegenSuche.trim().toLowerCase();
            const gewaehlt = new Set(this.form?.participant_ids || []);
            return this.kollegen
                .filter(k => !gewaehlt.has(k.id) && (!suche || k.name.toLowerCase().includes(suche)))
                .slice(0, suche ? 20 : 8);
        },
        kollegeName(id) {
            return this.kollegen.find(k => k.id === id)?.name
                || (this.form?.participants || []).find(p => p.id === id)?.name
                || 'Unbekannt';
        },
        kollegeHinzufuegen(id) {
            if (!this.form.participant_ids.includes(id)) this.form.participant_ids.push(id);
            this.kollegenSuche = '';
            this.aendern();
        },
        kollegeEntfernen(id) {
            this.form.participant_ids = this.form.participant_ids.filter(x => x !== id);
            this.aendern();
        },

        // ── Aus der Akte übernehmen (letzte 60 Tage) ──
        async akteUmschalten() {
            this.akteOffen = !this.akteOffen;
            if (!this.akteOffen || this.akte) return;
            this.akteLaedt = true;
            try {
                const von = new Date(); von.setDate(von.getDate() - 60);
                const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                const params = new URLSearchParams({ date_from: iso(von), date_to: heuteIso() });
                const { resp, json } = await anfrage(`/paed-diary/schueler/${this.schuelerId}/data?${params}`);
                if (!resp.ok) throw new Error(fehlermeldung(json, 'Die Akte konnte nicht geladen werden.'));
                const ziel = (json.goals || []).find(z => !z.achieved_at);
                this.akte = {
                    eintraege: (json.entries || []).slice(0, 30),
                    aufgaben: (json.tasks || []).filter(t => t.status !== 'closed'),
                    ziel,
                };
            } catch (e) {
                this.meldung = e.message;
                this.akteOffen = false;
            } finally {
                this.akteLaedt = false;
            }
        },
        kurz(text, laenge = 80) {
            const t = (text || '').replace(/\s+/g, ' ').trim();
            return t.length > laenge ? t.slice(0, laenge - 1) + '…' : t;
        },
        ausEintrag(e) {
            this.punktHinzufuegen({ title: this.kurz(e.content), preparation: `Eintrag vom ${e.formatted_date}${e.user ? ' (' + e.user + ')' : ''}: ${e.content}` });
        },
        ausAufgabe(t) {
            this.punktHinzufuegen({ title: this.kurz(t.title), preparation: `Offene Aufgabe${t.due_date ? ', fällig ' + t.due_date : ''}${t.description ? ': ' + t.description : ''}` });
        },
        ausZiel(z) {
            this.punktHinzufuegen({ title: 'Ziel: ' + this.kurz(z.goal_text, 70), preparation: `Ziel seit ${z.formatted_created_at || ''}: ${z.goal_text}` });
        },

        // ── Aktionen ──
        async aktion(pfad, bestaetigung = null) {
            if (bestaetigung && !confirm(bestaetigung)) return;
            if (!(await this.speicherAbwarten())) {
                this.meldung = 'Bitte zuerst die offenen Änderungen speichern.';
                return;
            }
            this.aktionLaeuft = true;
            this.meldung = '';
            try {
                const { resp, json } = await anfrage(`/paed-diary/gespraeche/${this.form.id}/${pfad}`, 'POST');
                if (!resp.ok) throw new Error(fehlermeldung(json, 'Die Aktion ist fehlgeschlagen.'));
                this.$store.elterngespraeche.ersetzen(json.data);
                this.form = alsFormular(json.data);
                this.zuruecksetzen();
            } catch (e) {
                this.meldung = e.message;
            } finally {
                this.aktionLaeuft = false;
            }
        },
        abschliessen() {
            this.aktion('abschliessen', 'Protokoll abschließen? Danach kann es nur noch gelesen werden (Wiederöffnen nur durch die Erstellerin/den Ersteller).');
        },
        absagen() {
            this.aktion('absagen', 'Gespräch absagen? Der Kalendertermin wird entfernt.');
        },
        wiederOeffnen() {
            this.aktion('wieder-oeffnen');
        },
        async loeschen() {
            if (!confirm('Gespräch mit Protokoll endgültig löschen?')) return;
            clearTimeout(this.timer);
            this.aktionLaeuft = true;
            try {
                const { resp, json } = await anfrage(`/paed-diary/gespraeche/${this.form.id}`, 'DELETE');
                if (!resp.ok) throw new Error(fehlermeldung(json, 'Das Gespräch konnte nicht gelöscht werden.'));
                this.$store.elterngespraeche.entfernen(this.form.id);
                this.dirty = false;
                this.offen = false;
                this.form = null;
            } catch (e) {
                this.meldung = e.message;
            } finally {
                this.aktionLaeuft = false;
            }
        },
        async uebernehmen(a, art) {
            if (!(await this.speicherAbwarten()) || !a.id) {
                this.meldung = 'Die Vereinbarung wird noch gespeichert – bitte gleich noch einmal versuchen.';
                return;
            }
            try {
                const { resp, json } = await anfrage(`/paed-diary/gespraeche/vereinbarungen/${a.id}/uebernehmen`, 'POST', { type: art });
                if (!resp.ok) throw new Error(fehlermeldung(json, 'Die Übernahme ist fehlgeschlagen.'));
                a.task_id = json.data.task_id;
                a.goal_id = json.data.goal_id;
            } catch (e) {
                this.meldung = e.message;
            }
        },
        async pdfOeffnen() {
            // Fenster sofort öffnen (sonst blockiert der Browser das Popup nach dem Warten)
            const fenster = window.open('', '_blank');
            await this.speicherAbwarten();
            const url = `/paed-diary/gespraeche/${this.form.id}/protokoll.pdf`;
            if (fenster) fenster.location = url; else window.location = url;
        },
        async folgegespraech() {
            const f = this.form;
            await this.neu({
                date: f.next_date || heuteIso(),
                reason: 'Folgegespräch zum Gespräch vom ' + datumDe(f.date),
                parents: f.parents,
                participant_ids: [...f.participant_ids],
                participants: f.participants || [],
                confidential: f.confidential,
                topics: f.agreements.filter(a => a.text.trim()).map(a => ({
                    key: schluessel(),
                    title: 'Vereinbarung: ' + this.kurz(a.text, 70),
                    preparation: a.text + (a.due_date ? ' (bis ' + datumDe(a.due_date) + ')' : ''),
                    result: '',
                })),
            });
        },
    }));
}
