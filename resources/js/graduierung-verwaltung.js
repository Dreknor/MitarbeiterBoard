/**
 * Verwaltung der Graduierungssysteme (admin/grading) – Vite-Entrypoint.
 * Design der neuen Ansicht des Pädagogischen Tagebuchs (pdn-*, eigenes CSS mit pdn-gv-*).
 *
 *   graduierungVerwaltung(): Systemauswahl, Tabs Stufen/Fragen, Seitenleisten zum Bearbeiten,
 *   Bestätigungsdialoge, Sortieren per Drag & Drop (SortableJS, speichert sofort per fetch),
 *   Fragenfilter nach Stufe (Fragen ohne Zuordnung gelten für alle Stufen).
 *
 * Gewähltes System und Tab überstehen das Absenden der Formulare (Redirect auf die Übersicht)
 * über sessionStorage – reine Bequemlichkeit, die Seite funktioniert auch ohne.
 */

import '../css/paed-diary-neu.css';
import '../css/graduierung-verwaltung.css';
import Sortable from 'sortablejs';

const SPEICHER = 'graduierungVerwaltung';

function lesen() {
    try {
        return JSON.parse(window.sessionStorage.getItem(SPEICHER) || '{}') || {};
    } catch (e) {
        return {};
    }
}

function schreiben(wert) {
    try { window.sessionStorage.setItem(SPEICHER, JSON.stringify(wert)); } catch (e) { /* ohne Speicher weiter */ }
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function registerGraduierungVerwaltung(Alpine) {
    Alpine.data('graduierungVerwaltung', (konfig) => ({
        systeme: konfig.systeme || [],
        systemId: null,
        tab: 'stufen',
        filterStufe: 0,
        stufe: null,          // Kopie der bearbeiteten Stufe (Seitenleiste)
        frage: null,          // Kopie der bearbeiteten Frage (Seitenleiste)
        vorschau: '',         // Objekt-URL des gewählten Stufenbilds
        systemDialog: false,
        loeschen: null,       // { url, titel, text }
        meldung: '',
        sortierer: [],

        init() {
            const gemerkt = lesen();
            const vorhanden = this.systeme.some(s => s.id === gemerkt.systemId);
            this.systemId = vorhanden ? gemerkt.systemId : (this.systeme[0]?.id ?? null);
            this.tab = gemerkt.tab === 'fragen' ? 'fragen' : 'stufen';

            this.$nextTick(() => this.sortierbarMachen());
            this.$watch('systemId', () => {
                this.filterStufe = 0;
                this.$nextTick(() => this.sortierbarMachen());
            });
            this.$watch('filterStufe', () => this.$nextTick(() => this.sortierbarMachen()));
        },

        get system() { return this.systeme.find(s => s.id === this.systemId) || null; },

        get sichtbareFragen() { return (this.system?.fragen || []).filter(f => this.frageSichtbar(f)); },

        waehleSystem(id) {
            this.systemId = id;
            this.merken();
        },

        tabSetzen(tab) {
            this.tab = tab;
            this.merken();
        },

        merken() {
            schreiben({ systemId: this.systemId, tab: this.tab });
        },

        // ── Anzeige ──
        frageSichtbar(f) {
            return !this.filterStufe || !f.stufen.length || f.stufen.includes(this.filterStufe);
        },

        stufenNamen(f) {
            return (this.system?.stufen || []).filter(st => f.stufen.includes(st.id)).map(st => st.name);
        },

        /** Anzahl der Fragen einer Stufe: eigene und solche für alle Stufen */
        fragenText(st) {
            const fragen = (this.system?.fragen || []).filter(f => f.aktiv);
            const eigene = fragen.filter(f => f.stufen.includes(st.id)).length;
            const alle = fragen.filter(f => !f.stufen.length).length;
            const teile = [];
            teile.push(eigene === 1 ? '1 eigene Frage' : eigene + ' eigene Fragen');
            if (alle) teile.push(alle === 1 ? '1 Frage für alle Stufen' : alle + ' Fragen für alle Stufen');
            return teile.join(' · ');
        },

        zeigeFragenDerStufe(st) {
            this.filterStufe = st.id;
            this.tabSetzen('fragen');
        },

        // ── Bearbeiten ──
        stufeBearbeiten(st) {
            this.vorschauFreigeben();
            const naechste = Math.max(-1, ...(this.system?.stufen || []).map(x => x.sort_order)) + 1;
            this.stufe = st
                ? { ...st }
                : { id: null, name: '', slug: '', symbol: '', bild: null, standard: false, sort_order: naechste };
        },

        frageBearbeiten(f) {
            const naechste = Math.max(-1, ...(this.system?.fragen || []).map(x => x.sort_order)) + 1;
            this.frage = f
                ? { ...f, stufen: [...f.stufen] }
                // Neue Frage im Stufenfilter: diese Stufe vorauswählen
                : { id: null, text: '', aktiv: true, stufen: this.filterStufe ? [this.filterStufe] : [], sort_order: naechste };
        },

        stufeUmschalten(id, an) {
            this.frage.stufen = an ? [...new Set([...this.frage.stufen, id])] : this.frage.stufen.filter(x => x !== id);
        },

        bildGewaehlt(event) {
            this.vorschauFreigeben();
            const datei = event.target.files?.[0];
            this.vorschau = datei ? URL.createObjectURL(datei) : '';
        },

        vorschauFreigeben() {
            if (this.vorschau) URL.revokeObjectURL(this.vorschau);
            this.vorschau = '';
        },

        loeschenFragen(art, eintrag) {
            if (art === 'system') {
                this.loeschen = {
                    url: eintrag.loeschenUrl,
                    titel: `System „${eintrag.name}“ löschen?`,
                    text: 'Das geht nur, solange keine Klasse, Dokumentation oder Stufenvergabe daran hängt. Stufen und Fragen werden mitgelöscht.',
                };
            } else if (art === 'stufe') {
                const nurHier = (this.system?.fragen || []).filter(f => f.aktiv && f.stufen.length === 1 && f.stufen[0] === eintrag.id).length;
                this.loeschen = {
                    url: eintrag.loeschenUrl,
                    titel: `Stufe „${eintrag.name}“ löschen?`,
                    text: 'Das geht nur, solange kein Schüler die Stufe hat und keine Historie darauf verweist.'
                        + (nurHier ? ` ${nurHier === 1 ? 'Eine Frage, die' : nurHier + ' Fragen, die'} nur dieser Stufe zugeordnet ${nurHier === 1 ? 'ist, wird' : 'sind, werden'} deaktiviert.` : ''),
                };
            } else {
                this.loeschen = {
                    url: eintrag.loeschenUrl,
                    titel: 'Frage löschen?',
                    text: 'Gibt es bereits Antworten oder Einschätzungen, kann die Frage nicht gelöscht werden – deaktiviere sie dann stattdessen.',
                };
            }
            this.stufe = null;
            this.frage = null;
        },

        // ── Sortieren ──
        sortierbarMachen() {
            this.sortierer.forEach(s => s.destroy());
            this.sortierer = [];
            if (!this.system) return;

            const stufen = this.$refs.stufenListe;
            if (stufen) {
                this.sortierer.push(Sortable.create(stufen, {
                    handle: '.pdn-gv-griff', animation: 150, draggable: '.pdn-gv-zeile',
                    onEnd: (evt) => this.verschoben('stufen', evt, this.system.stufenSortierenUrl),
                }));
            }

            // Gefilterte Fragenliste nicht sortieren (Reihenfolge der ausgeblendeten wäre unklar)
            const fragen = this.$refs.fragenListe;
            if (fragen && !this.filterStufe) {
                this.sortierer.push(Sortable.create(fragen, {
                    handle: '.pdn-gv-griff', animation: 150, draggable: '.pdn-gv-zeile',
                    onEnd: (evt) => this.verschoben('fragen', evt, this.system.fragenSortierenUrl),
                }));
            }
        },

        async verschoben(art, evt, url) {
            if (evt.oldIndex === evt.newIndex) return;
            const liste = this.system[art];

            // DOM-Änderung von Sortable zurücknehmen, Alpine rendert aus den Daten neu
            const { item, from, oldIndex } = evt;
            from.removeChild(item);
            const uebrige = from.querySelectorAll(':scope > .pdn-gv-zeile');
            from.insertBefore(item, uebrige[oldIndex] || null);

            const vorher = [...liste];
            const [eintrag] = liste.splice(evt.oldIndex, 1);
            liste.splice(evt.newIndex, 0, eintrag);
            liste.forEach((x, i) => { x.sort_order = i; });

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ order: liste.map(x => x.id) }),
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok || json.status !== 'ok') throw new Error(json.message || '');
            } catch (e) {
                this.system[art] = vorher;
                vorher.forEach((x, i) => { x.sort_order = i; });
                this.meldung = 'Die Reihenfolge konnte nicht gespeichert werden.' + (e.message ? ' ' + e.message : '');
            }
        },
    }));
}

if (window.Alpine) {
    registerGraduierungVerwaltung(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) registerGraduierungVerwaltung(window.Alpine);
    });
}
